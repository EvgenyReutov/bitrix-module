<?php
namespace Instrum\Main\Tools\Table;

use Bitrix\Main\Application;
use Bitrix\Main\Entity,
    Bitrix\Main\Type;

class CheckQueueTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'stuff_check_queue';
    }

    public static function create()
    {
        try
        {
            $connection = Application::getInstance()->getConnection();
            if (!$connection->isTableExists(CheckQueueTable::getTableName()))
            {
                CheckQueueTable::getEntity()->createDbTable();
                return true;
            }
        } catch (\Bitrix\Main\SystemException $e)
        {
            return false;
        }
    }

    public static function getMap()
    {
        try
        {
            return array(
                new Entity\IntegerField('ID', array(
                    'primary' => true,
                    'autocomplete' => true
                )),
                new Entity\IntegerField('ORDER_ID', []),
                new Entity\IntegerField('PAYMENT_ID',
                    [
                        'validation' => function()
                        {
                            return [new Entity\Validator\Unique];
                        },
                    ]
                ),
                new Entity\DatetimeField('TIMESTAMP',
                    [
                        'default_value' => function () {
                            return new Type\Datetime();
                        }
                    ]
                ));

        } catch (\Exception $e)
        {
        }
    }

}

