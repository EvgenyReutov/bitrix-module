<?php

namespace Instrum\Main\AdvCake;

use Instrum\Main\AdvCake\Entity;

class OrderSuccess extends Entity{
    protected $pageType = 6;
    protected $orderId;
    protected $orderInfo;

    public function __construct($orderId)
    {
        $this->orderId = $orderId;
        $this->basket = parent::getBasket($orderId);
        parent::__construct();
    }

    public function get(&$jsBuffer)
    {
        if(!$this->orderId) return false;
        parent::get($jsBuffer);

        $order = \Bitrix\Sale\Order::load($this->orderId);

        $orderId = $order->getId();
        $orderPrice = $order->getPrice();
        $orderCoupon = "false";

        $couponList = \Bitrix\Sale\Internals\OrderCouponsTable::getList([
            'select' => ['COUPON'],
            'filter' => ['=ORDER_ID' => $this->orderId]
        ]);
        if ($coupon = $couponList->fetch())
        {
            $orderCoupon = self::getString($coupon['COUPON']);
        }

        $jsBuffer .= ".setOrderInfo(".implode(', ', [$orderId, $orderPrice, $orderCoupon]).")";

        return true;
    }

}