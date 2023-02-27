<?php
namespace Instrum\Main\Table\Dpd;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class CityesTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> CITY_ID string(100) mandatory
 * <li> COUNTRY_CODE string(10) optional
 * <li> COUNTRY_NAME string(100) optional
 * <li> REGION_CODE string(100) optional
 * <li> REGION_NAME string(100) optional
 * <li> CITY_CODE string(100) optional
 * <li> CITY_NAME string(100) optional
 * <li> ABBREVIATION string(100) optional
 * <li> INDEX_MIN string(100) optional
 * <li> INDEX_MAX string(100) optional
 * </ul>
 *
 * @package Bitrix\Cityes
 **/

class CityesTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return 'dpd_cityes';
	}

	/**
	 * Returns entity map definition.
	 *
	 * @return array
	 */
	public static function getMap()
	{
		return array(
			'ID' => array(
				'data_type' => 'integer',
				'primary' => true,
				'autocomplete' => true,
				'title' => Loc::getMessage('CITYES_ENTITY_ID_FIELD'),
			),
			'CITY_ID' => array(
				'data_type' => 'string',
				'required' => true,
				'validation' => array(__CLASS__, 'validateCityId'),
				'title' => Loc::getMessage('CITYES_ENTITY_CITY_ID_FIELD'),
			),
			'COUNTRY_CODE' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateCountryCode'),
				'title' => Loc::getMessage('CITYES_ENTITY_COUNTRY_CODE_FIELD'),
			),
			'COUNTRY_NAME' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateCountryName'),
				'title' => Loc::getMessage('CITYES_ENTITY_COUNTRY_NAME_FIELD'),
			),
			'REGION_CODE' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateRegionCode'),
				'title' => Loc::getMessage('CITYES_ENTITY_REGION_CODE_FIELD'),
			),
			'REGION_NAME' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateRegionName'),
				'title' => Loc::getMessage('CITYES_ENTITY_REGION_NAME_FIELD'),
			),
			'CITY_CODE' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateCityCode'),
				'title' => Loc::getMessage('CITYES_ENTITY_CITY_CODE_FIELD'),
			),
			'CITY_NAME' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateCityName'),
				'title' => Loc::getMessage('CITYES_ENTITY_CITY_NAME_FIELD'),
			),
			'ABBREVIATION' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateAbbreviation'),
				'title' => Loc::getMessage('CITYES_ENTITY_ABBREVIATION_FIELD'),
			),
			'INDEX_MIN' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateIndexMin'),
				'title' => Loc::getMessage('CITYES_ENTITY_INDEX_MIN_FIELD'),
			),
			'INDEX_MAX' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateIndexMax'),
				'title' => Loc::getMessage('CITYES_ENTITY_INDEX_MAX_FIELD'),
			),
		);
	}
	/**
	 * Returns validators for CITY_ID field.
	 *
	 * @return array
	 */
	public static function validateCityId()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for COUNTRY_CODE field.
	 *
	 * @return array
	 */
	public static function validateCountryCode()
	{
		return array(
			new Main\Entity\Validator\Length(null, 10),
		);
	}
	/**
	 * Returns validators for COUNTRY_NAME field.
	 *
	 * @return array
	 */
	public static function validateCountryName()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for REGION_CODE field.
	 *
	 * @return array
	 */
	public static function validateRegionCode()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for REGION_NAME field.
	 *
	 * @return array
	 */
	public static function validateRegionName()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for CITY_CODE field.
	 *
	 * @return array
	 */
	public static function validateCityCode()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for CITY_NAME field.
	 *
	 * @return array
	 */
	public static function validateCityName()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for ABBREVIATION field.
	 *
	 * @return array
	 */
	public static function validateAbbreviation()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for INDEX_MIN field.
	 *
	 * @return array
	 */
	public static function validateIndexMin()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for INDEX_MAX field.
	 *
	 * @return array
	 */
	public static function validateIndexMax()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
}