<?php

/**
 * I18nColumnsCommand
 *
 * Finds models that are using I18nColumnsBehavior and creates relevant migrations
 *
 * @uses CConsoleCommand
 * @license MIT
 * @author See https://github.com/neam/yii-i18n-columns/graphs/contributors
 */

class I18nColumnsCommand extends CConsoleCommand
{
    /**
     * @var string
     */
    public $migrationPath='application.migrations';

    /**
     * @var array
     */
    public $models = array();

    /**
     * @var array
     */
    public $languages = array();

    /**
     * Source language
     *
     * @var string
     */
    public $sourceLanguage;

    /**
     * @var array
     */
    public $up = array();

    /**
     * @var array
     */
    public $down = array();

    /**
     * If we should be verbose
     *
     * @var bool
     */
    private $_verbose = false;

    /**
     * Array of columns to create
     *
     * @var array
     */
    public $columns = array();

    /**
     * Write a string to standard output if we're verbose
     *
     * @param $string
     */
    public function d($string)
    {
        if ($this->_verbose) {
            print "\033[37m" . $string . "\033[30m";
        }
    }

    /**
     * Execute the command
     *
     * @param array $args
     * @return bool|int
     */
    public function run($args)
    {
        if (in_array('--verbose', $args)) {
            $this->_verbose = true;
        }

        //
        $this->d("Loading languages\n");
        $this->_loadLanguages();

        $this->models = $this->_getModels();

        if (sizeof($this->models) > 0) {
            $this->_createMigration();
        } else {
            echo "Found no models with a i18nColumns() method";
        }
    }

    /**
     * Create the migration files
     */
    protected function _createMigration()
    {
        $this->d("Creating the migration...\n");
        foreach ($this->models as $modelName => $modelClass) {
            $this->d("\t...$modelName: \n");
            foreach ($this->languages as $lang) {
                $this->d("\t\t$lang: \n");
                $this->_processLang($lang, $modelClass);
            }
            $this->d("\n");
        }

        $this->_createMigrationFile();
    }

    /**
     * @param $lang
     * @param $model
     */
    protected function _processLang($lang, $model)
    {
        $behaviors = $model->behaviors();
        foreach ($behaviors['i18n-columns']['translationAttributes'] as $translationAttribute) {

            $newName = $translationAttribute . '_' . $lang;
            $sourceLanguageAttribute = $translationAttribute . '_' . $this->sourceLanguage;

            // Determine source column
            $attribute = null;
            if ($this->_checkColumnExists($model, $translationAttribute)) {
                $attribute = $translationAttribute;
            } elseif ($this->_checkColumnExists($model, $sourceLanguageAttribute)) {
                $attribute = $sourceLanguageAttribute;
            } else {
                throw new CException("No source attribute was found (neither $translationAttribute nor $sourceLanguageAttribute found in {$model->tableName()})");
            }

            $this->d("\t\t\t$newName ($attribute)\n");

            if (!isset($model->metaData->columns[$newName])) {

                // Foreign key checks
                $attributeFk = null;
                if (isset($model->metaData->tableSchema->foreignKeys[$attribute])) {

                    $attributeFk = Yii::app()->db->createCommand(
                        "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_NAME = :table_name AND COLUMN_NAME = :column_name"
                    )->queryRow(
                            true,
                            array(
                                ':table_name' => $model->tableName(),
                                ':column_name' => $attribute,
                            )
                        );

                    $attributeFk["rules"] = Yii::app()->db->createCommand(
                        "SELECT UPDATE_RULE, DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name"
                    )->queryRow(
                            true,
                            array(
                                ':table_name' => $model->tableName(),
                                ':constraint_name' => $attributeFk["CONSTRAINT_NAME"],
                            )
                        );

                }

                // Rename columns back and forth for the source language column (avoiding data loss compared to dropping and creating a new column instead)
                if ($lang == $this->sourceLanguage && $attribute != $sourceLanguageAttribute) {
                    // Remove fks before rename
                    if (!is_null($attributeFk)) {
                        $this->up[] = $this->down[] = '$this->dropForeignKey(\'' . $attributeFk["CONSTRAINT_NAME"]
                            . '\', \'' . $model->tableName() . '\');';
                    }
                    $this->up[] = '$this->renameColumn(\'' . $model->tableName() . '\', \'' . $attribute
                        . '\', \'' . $newName . '\');';
                    $this->down[] = '$this->renameColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\', \'' . $attribute . '\');';
                    // Add fks again after rename
                    if (!is_null($attributeFk)) {
                        $this->up[] = '$this->addForeignKey(\'' . $attributeFk["CONSTRAINT_NAME"]
                            . '\', \'' . $model->tableName()
                            . '\', \'' . $newName
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][0]
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][1]
                            . '\', \'' . $attributeFk["rules"]["DELETE_RULE"]
                            . '\', \'' . $attributeFk["rules"]["UPDATE_RULE"] . '\');';
                        $this->down[] = '$this->addForeignKey(\'' . $attributeFk["CONSTRAINT_NAME"]
                            . '\', \'' . $model->tableName()
                            . '\', \'' . $attribute
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][0]
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][1]
                            . '\', \'' . $attributeFk["rules"]["DELETE_RULE"]
                            . '\', \'' . $attributeFk["rules"]["UPDATE_RULE"] . '\');';
                    }
                } else {
                    $this->up[] = '$this->addColumn(\'' . $model->tableName() . '\', \'' . $newName
                        . '\', \'' . $this->_getColumnDbType($model, $attribute) . '\');';
                    $this->down[] = '$this->dropColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\');';
                    // Replicate out-going foreign keys
                    if (!is_null($attributeFk)) {
                        $this->up[] = '$this->addForeignKey(\'' . $attributeFk["CONSTRAINT_NAME"] . '_' . $lang
                            . '\', \'' . $model->tableName()
                            . '\', \'' . $newName
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][0]
                            . '\', \'' . $model->metaData->tableSchema->foreignKeys[$attribute][1] . '\');';
                        // No down-command necessary since the foreign key will be removed when the column is removed
                    }
                }
            }
        }
    }

    /**
     * @param $model
     * @param $column
     * @return bool
     */
    protected function _checkColumnExists($model, $column)
    {
        return isset($model->metaData->columns[$column]);
    }

    /**
     * @param $model
     * @param $column
     * @return string
     */
    protected function _getColumnDbType($model, $column)
    {
        $data = $model->metaData->columns[$column];
        $isNull = $data->allowNull ? "null" : "not null";

        return $data->dbType . ' ' . $isNull;
    }

    /**
     * Load languages from main config.
     *
     * @access protected
     */
    protected function _loadLanguages()
    {
        // Load main.php config file
        $file = realpath(Yii::app()->basePath) . '/config/main.php';
        if (!file_exists($file)) {
            print("Config not found\n");
            exit("Error loading config file $file.\n");
        } else {
            $config = require($file);
            $this->d("Config loaded\n");
        }

        if (!isset($config['components']['langHandler']['languages'])) {
            exit("Your Yii application has no configured languages.\n");
        }

        if (!isset($config['language'])) {
            exit("Please, define a default language in the config file.\n");
        }

        $this->languages = $config['components']['langHandler']['languages'];
        $this->sourceLanguage = $config['language'];
    }

    /**
     * Create migration file
     */
    protected function _createMigrationFile()
    {
        if (count($this->up) == 0) {
            exit("Database up to date\n");
        }

        $migrationName = 'm' . gmdate('ymd_His') . '_i18n';

        $phpCode = '<?php
class ' . $migrationName . ' extends CDbMigration
{
    public function up()
    {
        ' . implode("\n        ", $this->up) . '
    }

    public function down()
    {
      ' . implode("\n      ", $this->down) . '
    }
}' . "\n";

        $migrationsDir = Yii::getPathOfAlias($this->migrationPath);
        if (!realpath($migrationsDir)) {
            die(sprintf('Please create migration directory %s first', $migrationsDir));
        }

        $migrationFile = $migrationsDir . '/' . $migrationName . '.php';
        $f = fopen($migrationFile, 'w') or die("Can't open file");
        fwrite($f, $phpCode);
        fclose($f);

        print "Migration successfully created.\n";
        print "See $migrationName\n";
        print "To apply migration enter: ./yiic migrate\n";
    }

    // Originally from gii-template-collection / fullCrud / FullCrudGenerator.php
    protected function _getModels()
    {
        $models = array();
        $aliases = array();
        $aliases[] = 'application.models';
        foreach (Yii::app()->getModules() as $moduleName => $config) {
            if ($moduleName != 'gii') {
                $aliases[] = $moduleName . ".models";
            }
        }

        foreach ($aliases as $alias) {
            if (!is_dir(Yii::getPathOfAlias($alias))) {
                continue;
            }
            $files = scandir(Yii::getPathOfAlias($alias));
            Yii::import($alias . ".*");
            foreach ($files as $file) {
                if ($fileClassName = $this->_checkFile($file, $alias)) {
                    $classname = sprintf('%s.%s', $alias, $fileClassName);
                    Yii::import($classname);
                    try {
                        $model = @new $fileClassName;
                        if (method_exists($model, 'behaviors')) {
                            $behaviors = $model->behaviors();
                            if (isset($behaviors['i18n-columns']) && strpos(
                                    $behaviors['i18n-columns']['class'],
                                    'I18nColumnsBehavior'
                                ) !== false
                            ) {
                                $models[$classname] = $model;
                            }
                        }
                    } catch (ErrorException $e) {
                        break;
                    } catch (CDbException $e) {
                        break;
                    } catch (Exception $e) {
                        break;
                    }
                }
            }
        }

        return $models;
    }

    // Imported from gii-template-collection / fullCrud / FullCrudGenerator.php
    protected function _checkFile($file, $alias = '')
    {
        if (substr($file, 0, 1) !== '.' && substr($file, 0, 2) !== '..' && substr(
                $file,
                0,
                4
            ) !== 'Base' && $file != 'GActiveRecord' && strtolower(substr($file, -4)) === '.php'
        ) {
            $fileClassName = substr($file, 0, strpos($file, '.'));
            if (class_exists($fileClassName) && is_subclass_of($fileClassName, 'CActiveRecord')) {
                $fileClass = new ReflectionClass($fileClassName);
                if ($fileClass->isAbstract()) {
                    return null;
                } else {
                    return $models[] = $fileClassName;
                }
            }
        }
    }

}
