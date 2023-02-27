<?php
namespace Instrum\Main\Order;

use Bitrix\Main,
    Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

/**
 * Class TraceTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> type string(255) optional
 * <li> value string(255) optional
 * <li> date_insert datetime optional
 * </ul>
 *
 * @package Bitrix\Order
 **/

class TraceTable extends Main\Entity\DataManager
{
    const ORDER_ID = 'order_id';
    const DATE = 'date_insert';
    const TYPE = 'type';
    const VALUE = 'value';
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'sc_order_trace';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return array(
            'id' => array(
                'data_type' => 'integer',
                'primary' => true,
                'autocomplete' => true,
            ),
            'order_id' => array(
                'data_type' => 'integer',
            ),
            'type' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateType'),
            ),
            'value' => array(
                'data_type' => 'string',
                'validation' => array(__CLASS__, 'validateValue'),
            ),
            'date_insert' => array(
                'data_type' => 'datetime',
            ),
        );
    }

    /**
     * Returns validators for type field.
     *
     * @return array
     * @throws Main\ArgumentTypeException
     */
    public static function validateType()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    /**
     * Returns validators for value field.
     *
     * @return array
     * @throws Main\ArgumentTypeException
     */
    public static function validateValue()
    {
        return array(
            new Main\Entity\Validator\Length(null, 255),
        );
    }

    public static function getByType($type)
    {
        return static::yieldList(['filter' => ['type' => $type]]);
    }

    public static function yieldList(array $parameters = array())
    {
        $rs = static::getList($parameters);
        while($row = $rs->fetch())
        {
            yield $row;
        }
    }
}