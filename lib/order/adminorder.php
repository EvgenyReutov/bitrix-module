<?php
namespace Instrum\Main\Order;

use Bitrix\Main\Loader;
use Bitrix\Sale\Order;

class AdminOrder
{
    public function __construct()
    {
        Loader::includeModule('sale');
    }

    public function setCancelEnumProp($propCode, $newValue, Order $order)
    {
        $needSave = false;
        $list = $this->getOrderPropEnumValues($propCode, $order);

        $needItem = array_shift(array_filter($list, function($element) use ($newValue) {
            return $element['NAME'] == $newValue;
        }));

        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $property) {
            if ($property->getField('CODE') == $propCode) {
                if ($needItem['VALUE'])
                    $property->setValue($needItem['VALUE']);
                else
                    $property->setValue('');
                $needSave = true;
                break;
            }
        }

        if ($needSave)
            $order->save();
    }

    public function getOrderPropEnumValues($propCode, Order $order)
    {
        $result = [];
        $currentValue = '';
        $propertyCollection = $order->getPropertyCollection();
        foreach ($propertyCollection as $property) {
            if ($property->getField('CODE') == $propCode) {
                $currentValue = $property->getValue();
                break;
            }
        }
        $dbRes = \Bitrix\Sale\Property::getList([
            //'select' => ['ID'],
            'filter' => [
                'CODE' => $propCode,
                'PERSON_TYPE_ID' => $order->getPersonTypeId(),
            ],
            'group' => ['ID'],
            'order' => ['ID' => 'DESC']
        ]);
        if ($property = $dbRes->fetch())
        {
            $db_vars = \CSaleOrderPropsVariant::GetList(
                ['SORT' => 'ASC'],
                ['ORDER_PROPS_ID' => $property['ID']]
            );
            while ($vars = $db_vars->Fetch())
            {
                if ($currentValue == $vars['VALUE'])
                    $vars['SELECTED'] = 1;

                $result[$vars['ID']] = $vars;
            }
        }
        return $result;
    }
}