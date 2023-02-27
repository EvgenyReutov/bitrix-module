<?
namespace Instrum\Main\Table\Matrix;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class SchemesDetailTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SCHEME_ID int mandatory
 * <li> STORE_ID int mandatory
 * <li> CHAIN string optional
 * </ul>
 *
 * @package Bitrix\Api
 **/

class SchemesDetailTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return '1c_api_schemes_detail';
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
				'title' => Loc::getMessage('SCHEMES_DETAIL_ENTITY_ID_FIELD'),
			),
			'SCHEME_ID' => array(
				'data_type' => 'integer',
				'required' => true,
				'title' => Loc::getMessage('SCHEMES_DETAIL_ENTITY_SCHEME_ID_FIELD'),
			),
			'STORE_ID' => array(
				'data_type' => 'integer',
				'required' => true,
				'title' => Loc::getMessage('SCHEMES_DETAIL_ENTITY_STORE_ID_FIELD'),
			),
			'CHAIN' => array(
				'data_type' => 'text',
				'title' => Loc::getMessage('SCHEMES_DETAIL_ENTITY_CHAIN_FIELD'),
			),
		);
	}
}