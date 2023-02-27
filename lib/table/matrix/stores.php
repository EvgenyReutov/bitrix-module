<?
namespace Instrum\Main\Table\Matrix;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class StoresTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> STORE_ID int mandatory
 * <li> DELIVERY_ID int mandatory
 * <li> PERIOD int optional
 * <li> REGION_RU string(255) optional
 * <li> CITY_RU string(255) optional
 * </ul>
 *
 * @package Bitrix\Api
 **/

class StoresTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return '1c_api_stores';
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
				'title' => Loc::getMessage('STORES_ENTITY_ID_FIELD'),
			),
			'STORE_ID' => array(
				'data_type' => 'integer',
				'required' => true,
				'title' => Loc::getMessage('STORES_ENTITY_STORE_ID_FIELD'),
			),
			'DELIVERY_ID' => array(
				'data_type' => 'integer',
				'required' => true,
				'title' => Loc::getMessage('STORES_ENTITY_DELIVERY_ID_FIELD'),
			),
			'PERIOD' => array(
				'data_type' => 'integer',
				'title' => Loc::getMessage('STORES_ENTITY_PERIOD_FIELD'),
			),
			'REGION_RU' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateRegionRu'),
				'title' => Loc::getMessage('STORES_ENTITY_REGION_RU_FIELD'),
			),
			'CITY_RU' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateCityRu'),
				'title' => Loc::getMessage('STORES_ENTITY_CITY_RU_FIELD'),
			),
		);
	}
	/**
	 * Returns validators for REGION_RU field.
	 *
	 * @return array
	 */
	public static function validateRegionRu()
	{
		return array(
			new Main\Entity\Validator\Length(null, 255),
		);
	}
	/**
	 * Returns validators for CITY_RU field.
	 *
	 * @return array
	 */
	public static function validateCityRu()
	{
		return array(
			new Main\Entity\Validator\Length(null, 255),
		);
	}
}