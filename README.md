Yii Extension: I18nColumns
============

Transparent attribute translation for ActiveRecords, without requiring lookup tables for translated field contents.

Features
======

 * Eases the creation of multilingual ActiveRecords in a project
 * Automatically loads the application language by default
 * Translations are stored directly in the model using separate columns for each language
 * Console command automatically creates migrations for the necessary database changes

Setup
=============

## Installation

Ensure that you have the following in your composer.json:

    "repositories":[
        {
            "type": "vcs",
            "url": "https://github.com/neam/yii-i18n-columns"
        },
        ...
    ],
    "require":{
        "neam/yii-i18n-columns":"@dev",
        ...
    },

Then install through composer:

    php composer.php install neam/yii-i18n-columns

If you don't use composer, clone or download this project into /path/to/your/app/vendor/neam/yii-i18n-columns

## Configure

#### Add Alias to both main.php and console.php
    'aliases' => array(
        ...
        'vendor'  => dirname(__FILE__) . '/../../vendor',
        'i18n-columns' => 'vendor.neam.yii-i18n-columns',
        ...
    ),

## Import the behavior in main.php

    'import' => array(
        ...
        'i18n-columns.behaviors.I18nColumnsBehavior',
        ...
    ),


## Reference the translate command in console.php

    'commandMap' => array(
        ...
        'i18n-columns'    => array(
            'class' => 'i18n-columns.commands.I18nColumnsCommand',
        ),
        ...
    ),


## Configure models to be multilingual

### 1. Add the behavior to the models that you want multilingual

    public function behaviors()
    {
        return array(
            'i18n-columns' => array(
                 'class' => 'I18nColumnsBehavior',
                 'translationAttributes' => array(
                      'title',
                      'slug',
                      'etc',
                 ),
            ),
        );
    }

### 2. Create migration from command line:

`./yiic i18n-columns`

Prior to this, you should already have configured a default language (`$config['language']`) and available languages (`$config['components']['langHandler']['languages']`) for your app.

Run with `--verbose` to see more details.

### 3. Apply the generated migration:

`./yiic migrate`

This will rename the fields that are defined in translationAttributes to fieldname_defaultlanguagecode and add columns for the remaining languages.

Sample migration file:

	<?php
	class m130708_165204_i18n extends CDbMigration
	{
	    public function up()
	    {
		$this->renameColumn('section', 'title', 'title_en');
		$this->renameColumn('section', 'slug', 'slug_en');
		$this->addColumn('section', 'title_sv', 'varchar(255) null');
		$this->addColumn('section', 'slug_sv', 'varchar(255) null');
		$this->addColumn('section', 'title_de', 'varchar(255) null');
		$this->addColumn('section', 'slug_de', 'varchar(255) null');
	    }

	    public function down()
	    {
	      $this->renameColumn('section', 'title_en', 'title');
	      $this->renameColumn('section', 'slug_en', 'slug');
	      $this->dropColumn('section', 'title_sv');
	      $this->dropColumn('section', 'slug_sv');
	      $this->dropColumn('section', 'title_de');
	      $this->dropColumn('section', 'slug_de');
	    }
	}

### 5. Re-generate models

Use Gii as per the official documentation. After this, you have multilingual Active Records at your disposal :)

#Usage

Example usage with a Book model that has a multilingual *title* attribute.

All translations will be available through attribute suffix, ie `$book->title_en` for the english translation, `$book->title_sv` for the swedish translation. `$book->title` will be an alias for the currently selected language's translation.

## Fetching translations

     $book = Book::model()->findByPk(1);
     Yii::app()->language = 'en';
     echo $book->title; // Outputs 'The Alchemist'
     Yii::app()->language = 'sv';
     echo $book->title; // Outputs 'Alkemisten'
     echo $book->title_en; // Outputs 'The Alchemist'

## Saving a single translation

     Yii::app()->language = 'sv';
     $book->title = 'Djävulen bär Prada';
     $book->save(); // Saves 'Djävulen bär Prada' to Book.title_sv

## Saving multiple translations

     $book->title_en = 'The Devil Wears Prada';
     $book->title_sv = 'Djävulen bär Prada';
     $book->save(); // Saves both translations

## More examples

...can be found in tests/unit/I18nColumnsTest.php

# Changelog

### 0.1.0

- Renamed to I18nColumns (to clarify the underlying concept)
- More accurate model detection (not searching model source files for a hard-coded string...)
- Cleaned up (does not contain a complete Yii application, only the necessary extension files)
- Composer support
- Improved instructions directly in README
- Updated to work with Yii 1.1.13
- Unit tests

### 0.0.0

- Forked https://github.com/firstrow/STranslateableBehavior

# Credits

- [@firstrow](https://github.com/firstrow) for creating STranslateableBehavior which introduced the concept of column-based i18n for Yii
- [@mikehaertl](https://github.com/mikehaertl) for [the getter/setter logic](https://github.com/mikehaertl/translatable/blob/master/Translatable.php#L60)
- [@schmunk42](https://github.com/schmunk42) and [@tonydspaniard](https://github.com/tonydspaniard) for support
- [@clevertech](https://github.com/clevertech) for initial tests directory structure

FAQ
======

## Why use suffixed columns instead of one or many lookup tables?

### 1. Clarity

Your multilingual models will keep working as ordinary models, albeit with more fields than before. Your EER diagrams will only be cluttered with extra fields, not new translation tables and relations.

### 2. Simple usage

There is no need to create advanced join-helpers to access the translated attributes, they are simply attributes in the table to begin with. Thus, creating SQL to interact with translations is very straightforward:

`SELECT id, title_en AS title FROM book WHERE title = 'The Alchemist';`

### 3. Easy translation to all languages

After you have generated your CRUD for the multilingual model, you immediately have a translation interface for all languages. You can translate them side by side and easily spot missing translations.

### 4. Translation of related records while maintaining foreign keys

Do you have a image_id foreign key that should be point to a different image record for each language? Good news, you can still define your relations / foreign keys and keep database integrity checks intact (much harder when using lookup tables)

### 5. Decreased complexity = Flexibility

Several advantages, such as:

- Create SQL commands for all translations without requiring n joins where n is the amount of languages configured
- Easily add whole tables to Lucene indexes and be certain that all translated content is indexed

### 6. Why not?

Why not? :)

Running tests
==========

    cd vendor/neam/yii-i18n-columns
    php path/to/composer.phar install --dev
    cd tests
    ../vendor/bin/phpunit --verbose --debug

This should result in an output similar to:

	PHPUnit 3.7.22 by Sebastian Bergmann.

	Configuration read from /path/to/app/vendor/neam/yii-i18n-columns/tests/phpunit.xml


	Starting test 'I18nColumnsTest::ensureEmptyDb'.
	.
	Starting test 'I18nColumnsTest::getWithoutSuffix'.
	.
	Starting test 'I18nColumnsTest::setWithoutSuffix'.
	.
	Starting test 'I18nColumnsTest::saveSingleWithoutSuffix'.
	.
	Starting test 'I18nColumnsTest::fetchSingleWithoutSuffix'.
	.
	Starting test 'I18nColumnsTest::saveMultiple'.
	.

	Time: 0 seconds, Memory: 10.00Mb

	OK (6 tests, 28 assertions)