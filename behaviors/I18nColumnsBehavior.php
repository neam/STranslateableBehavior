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
	 * Set non-prefixed multilingual attributes to the current language
	 * 
	 * @access public
	 */
	public function afterFind($event)
	{
		$columns = $this->owner->i18nColumns();
		if (sizeof($columns) > 0) {
			foreach ($columns as $attr) {
				$attrName = $attr . '_' . Yii::app()->language;
				if (array_key_exists($attrName, $this->owner->attributes))
					$this->owner->$attr = $this->owner->$attrName;
			}
		}
	}

}
