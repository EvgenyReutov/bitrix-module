<?php

namespace Instrum\Main;

use Bitrix\Sale\Internals\BasketTable;

class LinkedItems{

    protected $order;
    protected $product;
    protected $limit = 10;

    public function __construct($order, $product)
    {
        $this->order = $order;
        $this->product = $product;
    }

    public function setLimit($limit = 10)
    {
        $this->limit = $limit;
        return $this;
    }

    public function getItems()
    {
        $orders = [];
        $products = [];

        $rs = BasketTable::getList([
            'filter' => [
                '!ORDER_ID' => $this->order,
                'PRODUCT_ID' =>$this->product
            ]
        ]);

        while($order = $rs->fetch())
        {
            $orders[] = $order['ORDER_ID'];
        }

        $rs = BasketTable::getList([
            'filter' => [
                'ORDER_ID' => $orders,
                '!PRODUCT_ID' =>$this->product
            ],
            'limit' => $this->limit
        ]);

        while($product = $rs->fetch())
        {
            $products[] = $product['PRODUCT_ID'];
        }

        return $products;

    }

}