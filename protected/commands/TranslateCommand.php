<?php

/**
 * TranslateCommand
 * Searches models that using TranslateBehavior and creates new migrations
 */
class TranslateCommand extends CConsoleCommand
{

    /**
     * @var array
     */
    public $models = array();

    /**
     * @var null
     */
    public $langs = null;

    /**
     * Source language
     *
     * @var string
     */
    public $sourceLang;

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
            print $string;
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
        $this->d("\033[37mLoading languages\n");
        $this->_loadLanguages();

		$this->models = $this->getModels();

        if (sizeof($this->models) > 0) {
            $this->_createMigration();
            } else {
			echo "Found no models with a translate() method";
        }
    }

    /**
     * Create the migration files
     */
    protected function _createMigration()
    {
        $this->d("Creating the migration...\n");
        foreach ($this->models as $modelName => $modelClass) {
            $this->d("\t...$modelName: ");
            foreach ($this->langs as $lang) {
                $this->d($lang);
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
        foreach ($model->translate() as $attribute) {
            $newName = $attribute . '_' . $lang;
            if (!isset($model->metaData->columns[$newName])
                && $this->_checkColumnExists($model, $attribute)) {
                // Rename columns back and forth
                if ($lang == $this->sourceLang) {
                    $this->d("Rename $attribute to $newName\n");
                    $this->up[] = '$this->renameColumn(\''. $model->tableName() . '\', \'' . $attribute
                        . '\', \'' . $newName . '\');';
                    $this->down[] = '$this->renameColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\', \'' . $attribute . '\');';
                } else {
                    $this->up[] = '$this->addColumn(\''. $model->tableName() . '\', \'' . $newName
                        . '\', \'' . $this->_getColumnDbType($model, $attribute) . '\');';
                    $this->down[] = '$this->dropColumn(\'' . $model->tableName() . '\', \''
                        . $newName . '\');';
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
     * Load model file and create new instance
     *
     * @param mixed $path Path to model
     * @access protected
     */
    protected function _loadModelFile($path)
    {
        $this->d("Loading model from $path\n");
        $class_name = str_replace('.php', '', basename($path));
        if (!class_exists($class_name, false)) {
            include($path);
            $model = new $class_name();
            if (method_exists($model, 'translate')) {
                $this->models[$class_name] = $model;
            }
        }
    }

    /**
     * Load languages from main config.
     *
     * @access protected
     */
    protected function _loadLanguages()
    {
        // Load main.php config file
        $file = realpath(dirname(__FILE__) . '/../config/main.php');
        if (!file_exists($file)) {
            print("Config not found\n");
            exit("Error loading config file main.php.\n");
        } else {
            $config = require($file);
            $this->d("Config loaded\n");
        }

        if (!isset($config['params']['languages'])) {
            exit("Please, define possible languages in config file.\n");
        }

        if (!isset($config['sourceLanguage'])) {
            exit("Please, define the source language in config file.\n");
        }

        $this->langs = $config['params']['languages'];
        $this->sourceLang = $config['sourceLanguage'];
    }

    /**
     * Create migration file
     */
    protected function _createMigrationFile()
    {
        if (count($this->up) == 0) {
            exit("Database up to date\n");
        }

        $migrationName = 'm' . gmdate('ymd_His') . '_translate';

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

        $migrationsDir = realpath(Yii::app()->basePath.'/migrations');
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
	protected function getModels() {
		$models = array();
		$aliases = array();
		$aliases[] = 'application.models';
		foreach (Yii::app()->getModules() as $moduleName => $config) {
			if($moduleName != 'gii')
				$aliases[] = $moduleName . ".models";
		}

		foreach ($aliases as $alias) {
			if (!is_dir(Yii::getPathOfAlias($alias))) continue;
			$files = scandir(Yii::getPathOfAlias($alias));
			Yii::import($alias.".*");
			foreach ($files as $file) {
				if ($fileClassName = $this->checkFile($file, $alias)) {
						$classname = sprintf('%s.%s',$alias,$fileClassName);
						Yii::import($classname);
					try {
						$model = @new $fileClassName;
						if (method_exists($model, 'translate')) {
							if (method_exists($model, 'behaviors')) {
								$behaviors = $model->behaviors();
								if (isset($behaviors['translate']) && strpos($behaviors['translate']['class'], 'STranslateableBehavior') !== false) {
									$models[$classname] = $model;
								}
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
	private function checkFile($file, $alias = '') {
		if (substr($file, 0, 1) !== '.'
				&& substr($file, 0, 2) !== '..'
				&& substr($file, 0, 4) !== 'Base'
			&& $file != 'GActiveRecord'
			&& strtolower(substr($file, -4)) === '.php') {
			$fileClassName = substr($file, 0, strpos($file, '.'));
			if (class_exists($fileClassName)
					&& is_subclass_of($fileClassName, 'CActiveRecord')) {
				$fileClass = new ReflectionClass($fileClassName);
				if ($fileClass->isAbstract())
					return null;
				else
					return $models[] = $fileClassName;
			}
		}
	}
	
}
