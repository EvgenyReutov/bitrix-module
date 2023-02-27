<?
namespace Instrum\Main\Table\Matrix;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class ProductsTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> SCHEME_ID int mandatory
 * </ul>
 *
 * @package Bitrix\Api
 **/

class ProductsTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return '1c_api_products';
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
				'title' => Loc::getMessage('PRODUCTS_ENTITY_ID_FIELD'),
			),
			'SCHEME_ID' => array(
				'data_type' => 'integer',
				'required' => true,
				'title' => Loc::getMessage('PRODUCTS_ENTITY_SCHEME_ID_FIELD'),
			),
		);
	}
}