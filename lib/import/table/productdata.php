<?php

namespace Instrum\Main\Import\Table;

use Bitrix\Main\Application;
use Bitrix\Main\Entity;

class ProductDataTable extends Entity\DataManager
{
    public static function getTableName()
    {
        return 'stuff_product_data';
    }

    public static function getMap()
    {
        return [
            new Entity\IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true
            ]),
            new Entity\IntegerField('FROM_ID', [
                'required' => true,
            ]),
            new Entity\StringField('LINKED_DATA', [
                'required' => true,
            ]),
            new Entity\IntegerField('TO_ID', [
            ]),
        ];
    }

    public static function insertRow($from_id, $linked_data)
    {
       static::add([
            'FROM_ID' => $from_id,
            'LINKED_DATA' => $linked_data
        ]);
    }

    public static function updateRow($to_id, $linked_data)
    {
        if($row = static::getRow(['filter' => ['LINKED_DATA' => $linked_data]]))
        {
            $row['TO_ID'] = $to_id;
            static::update($row['ID'], $row);
        }
    }

    public static function clear()
    {
        $connection = static::getEntity()->getConnection();
        $connection->queryExecute('DELETE FROM '.static::getTableName().' WHERE TO_ID=0 OR LINKED_DATA IS NULL');
    }

    public static function deleteAll()
    {
        $connection = static::getEntity()->getConnection();
        $connection->queryExecute('DELETE FROM '.static::getTableName());
    }

    public static function create()
    {
        try
        {
            $connection = Application::getInstance()->getConnection();
            if(!$connection->isTableExists(ProductDataTable::getTableName()))
            {
                ProductDataTable::getEntity()->createDbTable();
            }
        }
        catch (\Bitrix\Main\SystemException $e)
        {
        }
    }
}