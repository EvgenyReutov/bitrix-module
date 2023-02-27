<?
namespace Instrum\Main\Table\Matrix;

use Bitrix\Main,
	Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class SchemesTable
 * 
 * Fields:
 * <ul>
 * <li> ID int mandatory
 * <li> XML_ID string(100) mandatory
 * <li> NAME string(100) optional
 * </ul>
 *
 * @package Bitrix\Api
 **/

class SchemesTable extends Main\Entity\DataManager
{
	/**
	 * Returns DB table name for entity.
	 *
	 * @return string
	 */
	public static function getTableName()
	{
		return '1c_api_schemes';
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
				'title' => Loc::getMessage('SCHEMES_ENTITY_ID_FIELD'),
			),
			'XML_ID' => array(
				'data_type' => 'string',
				'required' => true,
				'validation' => array(__CLASS__, 'validateXmlId'),
				'title' => Loc::getMessage('SCHEMES_ENTITY_XML_ID_FIELD'),
			),
			'NAME' => array(
				'data_type' => 'string',
				'validation' => array(__CLASS__, 'validateName'),
				'title' => Loc::getMessage('SCHEMES_ENTITY_NAME_FIELD'),
			),
		);
	}
	/**
	 * Returns validators for XML_ID field.
	 *
	 * @return array
	 */
	public static function validateXmlId()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
	/**
	 * Returns validators for NAME field.
	 *
	 * @return array
	 */
	public static function validateName()
	{
		return array(
			new Main\Entity\Validator\Length(null, 100),
		);
	}
}