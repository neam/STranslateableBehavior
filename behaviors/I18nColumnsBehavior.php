<?php

/**
 * I18nColumnsBehavior
 * 
 * @uses CActiveRecordBehavior
 * @license MIT
 * @author See https://github.com/neam/yii-i18n-columns/graphs/contributors
 */
class I18nColumnsBehavior extends CActiveRecordBehavior
{

	/**
	 * @var array list of attributes to translate
	 */
	public $translationAttributes = array();

	/**
	 * Make translated attributes readable without requiring suffix
	 */
	public function __get($name)
	{
		if (!in_array($name, $this->translationAttributes))
			return parent::__get($name);

		$translated = $name . '_' . Yii::app()->language;
		if (array_key_exists($translated, $this->owner->attributes))
			return $this->owner->$translated;

		throw new Exception("Attribute '$translated' does not exist in model " . get_class($this->owner));
	}

	/**
	 * Make translated attributes writeable without requiring suffix
	 */
	public function __set($name, $value)
	{
		if (!in_array($name, $this->translationAttributes))
			return parent::__set($name, $value);

		$translated = $name . '_' . Yii::app()->language;
		if (array_key_exists($translated, $this->owner->attributes))
			$this->owner->$translated = $value;
		else
			throw new Exception("Attribute '$translated' does not exist in model " . get_class($this->owner));
	}

	/**
	 * Expose translatable attributes as readable
	 */
	public function canGetProperty($name)
	{
		return in_array($name, $this->translationAttributes) ? true : parent::canGetProperty($name);
	}

	/**
	 * Expose translatable attributes as writeable
	 */
	public function canSetProperty($name)
	{
		return in_array($name, $this->translationAttributes) ? true : parent::canSetProperty($name);
	}

	/**
	 * Mark the multilingual attributes as safe, so that forms that rely
	 * on setting attributes from post values works without modification.
	 * 
	 * @param CActiveRecord $owner
	 * @throws Exception
	 */
	public function attach($owner)
	{
		parent::attach($owner);
		if (!($owner instanceof CActiveRecord))
			throw new Exception('Owner must be a CActiveRecord class');

		$validators = $owner->getValidatorList();

		foreach ($this->translationAttributes as $name) {
			$validators->add(CValidator::createValidator('safe', $owner, $name, array()));
		}
	}

}
