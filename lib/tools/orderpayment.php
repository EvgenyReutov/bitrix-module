<?php

namespace Instrum\Main\Tools;

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Objectify\EntityObject;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Cashbox\Check;
use Bitrix\Sale\Cashbox\Internals\CashboxCheckTable;
use Bitrix\Sale\Cashbox\Internals\Pool;
use Bitrix\Sale\Order;
use Bitrix\Sale\Payment;
use Bitrix\Sale\Cashbox;
use Bitrix\Sale\Shipment;
use Bitrix\Sale\ShipmentItem;
use Instrum\Main\Tools\Table\CheckQueueTable;

class OrderPayment{
    public static function setup()
    {
        EventManager::getInstance()->RegisterEventHandler(
            "sale",
            "OnSalePaymentEntitySaved",
            'local.main',
            '\Instrum\Main\Tools\OrderPayment',
            'onPaymentSave'
        );
    }

    public static function normalizeOrder(Order $order)
    {
        $basket = [];

        /** @var Shipment $shipmet */
        $shipment = $order->getShipmentCollection()[0];

        /** @var ShipmentItem $shipmentItem */
        foreach ($shipment->getShipmentItemCollection() as $shipmentItem)
        {
            $basket[$shipmentItem->getBasketId()] = $shipmentItem;
        }

        /** @var BasketItem $basketItem */
        foreach($order->getBasket()->getBasketItems() as $basketItem)
        {
            if(!in_array($basketItem->getId(), array_keys($basket)))
            {
                $shipmentItem = $shipment->getShipmentItemCollection()->createItem($basketItem);
                $shipmentItem->setQuantity($basketItem->getQuantity());
            }
        }

        $shipment->save();

    }

    public static function readCheckQueue()
    {
        Loader::includeModule('sale');
        $time = time() - 60;

        $rs = CheckQueueTable::getList([
            'filter' => ['<TIMESTAMP' => DateTime::createFromTimestamp($time)]
        ]);

        /** @var EntityObject $row */
        while($row = $rs->fetchObject())
        {
            self::delayedTryPrintChecks($row->get('ORDER_ID'), $row->get('PAYMENT_ID'));
            CheckQueueTable::delete($row->getId());
        }
    }

    public static function delayedTryPrintChecks($order_id, $payment_id)
    {
        $order = Order::load($order_id);
        $payment = $order->getPaymentCollection()->getItemById($payment_id);

        if(!CashboxCheckTable::getRow([
            'filter' => [
                'ORDER_ID' => $order->getId(),
                'PAYMENT_ID' => $payment->getId()
            ]
        ]))
        {

            $relatedEntities = [];

            $paymentCollection = $order->getPaymentCollection();
            foreach ($paymentCollection as $cpayment)
            {
                if($cpayment->getId() !== $payment->getId())
                {
                    print_r([$cpayment->getId(), $payment->getId()]);
                    $relatedEntities[Check::PAYMENT_TYPE_ADVANCE][] = $cpayment;
                }
            }

            $shipmentCollection = $order->getShipmentCollection();
            /** @var \Bitrix\Sale\Shipment $shipment */
            foreach ($shipmentCollection as $shipment)
            {
                if (!$shipment->isSystem())
                {
                    $relatedEntities[Check::SHIPMENT_TYPE_NONE][] = $shipment;
                }
            }

            Cashbox\CheckManager::addByType([$payment], 'fullprepayment', $relatedEntities);
        }
    }

    public static function onPaymentSave(\Bitrix\Main\Event $event)
    {
        /** @var Payment $entity */
        $entity = $event->getParameter('ENTITY');
        $value = $event->getParameter('VALUES');

        if($value['PAID'] == 'N' && $entity->getField('PAID') == 'Y')
        {

            $order = $entity->getCollection()->getOrder();
            self::normalizeOrder($order);

            $ps = $entity->getPaySystem();
            if($ps && $ps->getField('CAN_PRINT_CHECK') == 'Y')
            {
                CheckQueueTable::add([
                    'ORDER_ID' => $order->getId(),
                    'PAYMENT_ID' => $entity->getId()
                ]);
            }
        }
    }

}