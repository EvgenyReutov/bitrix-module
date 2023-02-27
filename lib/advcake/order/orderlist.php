<?php

namespace Instrum\Main\AdvCake\Order;

use Bitrix\Main\Loader;
use Bitrix\Sale;
use XMLWriter;

class OrderList{
    protected $from;
    protected $to;

    private $format = 'd.m.Y';

    public function __construct()
    {
        $this->from = date($this->format, strtotime('today - 30 days'));
        $this->to = date($this->format, strtotime('today + 1 day'));
    }

    public function setDateFrom($time)
    {
        $this->from = date($this->format, $time);
        return $this;
    }

    public function setDateTo($time)
    {
        $this->to = date($this->format, $time);
        return $this;
    }

    protected function extendSectionProducts(&$arProducts)
    {
        $arProductsId = array_keys($arProducts);
        $arItemSections = [];
        $arSections = [];

        $rs = \CIBlockElement::GetList([], ['ID' => $arProductsId], false, false, ['ID', 'IBLOCK_SECTION_ID']);
        while($arItem = $rs->GetNext())
        {
            $arItemSections[$arItem['ID']] = $arItem['IBLOCK_SECTION_ID'];
        }

        $rs = \CIBlockSection::GetList([], ['ID' => array_values($arItemSections), false, ['ID', 'NAME']]);
        while($arSection = $rs->GetNext())
        {
            $arSections[$arSection['ID']] = $arSection;
        }

        foreach($arItemSections as $itemId => $sectionId)
        {
            $arProducts[$itemId]['categoryId'] = $sectionId;
            $arProducts[$itemId]['categoryName'] = $arSections[$sectionId]['NAME'];
        }
    }

    protected function getOrderBasketJson(\Bitrix\Sale\Order $order)
    {
        $result = [];
        $basket = $order->getBasket();

        $arProducts = [];
        /** @var Sale\BasketItem $item */
        foreach($basket->getBasketItems() as $item)
        {
            $arProducts[$item->getProductId()] = [
                'id' => $item->getProductId(),
                'name' => $item->getField('NAME'),
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }
        $this->extendSectionProducts($arProducts);

        foreach($arProducts as $arProduct)
        {
            $result[] = [
                'id' => $arProduct['id'],
                'name' => $arProduct['name'],
                'price' => $arProduct['price'],
                'quantity' => $arProduct['quantity'],
                'category' => $arProduct['categoryId'],
                'category_name' => $arProduct['categoryName'],
            ];
        }

        return json_encode($result);
    }

    private function toArray($values)
    {
        $map = [];

        foreach($values as $value)
        {
            list($key, $val) = explode(": ", $value);
            $map[$key] = $val;
        }

        return $map;
    }

    protected function getOrderProperties(\Bitrix\Sale\Order $order)
    {
        $result = [];

        /** @var \Bitrix\Sale\PropertyValue $property */
        foreach($order->getPropertyCollection() as $property)
        {
            switch($property->getField('CODE'))
            {
                case 'UTM_LAST':
                    $values = $this->toArray($property->getValue());
                    if($values['UTM_Source'] == 'advcake' || $values['Usource'] == 'advcake')
                    {
                        $result['orderTrackid'] = $values['trackid'];
                        $result['url'] = htmlspecialchars($values['url']);
                    }
                    break;
            }
        }

        return $result;
    }

    protected function getOrderBaseData(\Bitrix\Sale\Order $order)
    {
        $result = [];

        $orderStatus = 1;
        switch($order->getField('STATUS_ID'))
        {
            case 'Y':
            case 'NW':
            case 'F':
                $orderStatus = 2;
                break;
            case 'CA':
                $orderStatus = 3;
                break;
        }

        if($order->getField('CANCELED') == 'Y')
        {
            $orderStatus = 3;
        }

        $result['orderId'] = $order->getId();
        $result['orderPrice'] = $order->getPrice();
        $result['orderStatus'] = $orderStatus;
        $result['orderBasket'] = $this->getOrderBasketJson($order);

        if($order->getField('CANCELED') == 'Y')
        {
            $result['description'] = $order->getField('REASON_CANCELED');
        }

        $result['dateCreate'] = $order->getField('DATE_INSERT')->toString();
        $result['dateLastChange'] = $order->getField('DATE_UPDATE')->toString();

        $couponList = \Bitrix\Sale\Internals\OrderCouponsTable::getList([
            'select' => ['COUPON'],
            'filter' => ['=ORDER_ID' => $order->getId()]
        ]);
        if ($coupon = $couponList->fetch())
        {
            $result['coupon'] = $coupon['COUPON'];
        }

        return $result;
    }

    /**
     * @param XMLWriter $xmlWriter
     * @param Sale\Order $order
     */
    protected function writeOrderXml($xmlWriter, $order)
    {
        $nodes = array_merge($this->getOrderBaseData($order), $this->getOrderProperties($order));

        $xmlWriter->startElement('order');
        foreach ($nodes as $tag => $data) {
            $xmlWriter->writeElement($tag, $data);
        }
        $xmlWriter->endElement(); //order
    }

    protected function prepareOrderList()
    {
        $orders = [];
        $props = \Bitrix\Sale\Internals\OrderPropsValueTable::getList([
            'filter' => [
                'CODE' => 'UTM_LAST',
                '%VALUE' => 'advcake'
            ],
            'select' => ['ORDER_ID']
        ]);
        while($row = $props->fetch()) {
            $orders[] = $row['ORDER_ID'];
        }

        return $orders;
    }

    protected function getOrderListXml()
    {
        Loader::includeModule('sale');
        Loader::includeModule('iblock');


        $orders = $this->prepareOrderList();

        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);

        $xmlWriter->startDocument('1.0', 'UTF-8');
        $xmlWriter->startElement('orders');

        if($orders) {
            $rowset = Sale\Order::getList([
                'filter' => [
                    '><DATE_INSERT' => [$this->from, $this->to],
                    'ID' => $orders
                ],
                'select' => ['ID'],
                'order' => ['ID' => 'DESC']
            ]);

            while ($arOrder = $rowset->fetch()) {
                $order = Sale\Order::load($arOrder['ID']);
                $this->writeOrderXml($xmlWriter, $order);
            }
        }

        $xmlWriter->endElement(); // orders

        return $xmlWriter->outputMemory();
    }

    public function get()
    {
        header("Content-Type: text/xml");
        echo $this->getOrderListXml();
    }
}
