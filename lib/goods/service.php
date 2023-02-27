<?php


namespace Instrum\Main\Goods;

use CPrice;
use \RuntimeException;
use \CCatalogProduct;
use \CIBlockElement;
use \CSaleOrder;
use Bitrix\Sale;
use Bitrix\Currency;
use Bitrix\Main;

class Service
{
    // ИП Иванова
    //const API_KEY = '9D1AC6B2-DBDA-45A8-978C-E7A06EAEE997';
    // Юника
    const API_KEY = '24817B2E-526A-400C-B1F3-E7B7BD2C8379';
    const API_KEY_2 = 'E5549C12-BF03-41E3-9F39-480889E14A25';

    const GOODS_USER_ID  = 575;
    const DEFAULT_PAYMENT_SYSTEM_ID = 3;
    const DEFAULT_DELIVERY_ID = 20;
    const DEFAULT_PHONE_NUMBER = '00000000';
    const DEFAULT_CITY = '0000073738';
    const DEFAULT_STATUS = 'N';
    const CATALOG_XML_ID = 'cdb39091-91d2-4848-8b5e-7e462e15e50e';

    /** @var ApiClient[] */
    protected $api;

    /**
     * Service constructor.
     */
    public function __construct()
    {
        $this->api = [
            new ApiClient(static::API_KEY),
            new ApiClient(static::API_KEY_2)
        ];
    }

    /**
     * @return string
     */
    protected function getSiteId()
    {
        return Main\Context::getCurrent()->getSite();
    }

    /**
     * @return string
     */
    protected function getCurrencyCode()
    {
        return Currency\CurrencyManager::getBaseCurrency();
    }


    /**
     * @param $shipment
     * @return int|void
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\NotImplementedException
     * @throws Main\NotSupportedException
     * @throws Main\ObjectException
     * @throws Main\ObjectNotFoundException
     * @throws Main\SystemException
     */
    protected function addSingleOrder($shipment)
    {
        if(!is_array($shipment)) {
            throw new RuntimeException('Shipment data is broken');
        }
        if(empty($shipment['shipmentId'])) {
            throw new RuntimeException('Shipment id not provided');
        }
        if(!is_array($shipment['items'])) {
            throw new RuntimeException('Shipment items data is broken');
        }
        if(empty($shipment['items'])) {
            throw new RuntimeException('Shipment items are empty');
        }

        if(!empty($shipment['orderCode'])) {
            return null;
        }
        $existing = CSaleOrder::GetList([],[
            'PROPERTY_VAL_BY_CODE_GOODS_ORDER_NUMBER' => $shipment['shipmentId']
        ]);
        if($existing->Fetch()) {
            return null;
        }


        $preparedItems = [];
        foreach ($shipment['items'] as $shipmentItem) {
            $productId = $shipmentItem['offerId'];

            if(!empty($preparedItems[$productId])) {
                $preparedItems[$productId]['QUANTITY'] += $shipmentItem['quantity'];
            } else {
                $productElement = CIBlockElement::GetByID($productId)->Fetch();
                if(empty($productElement)) {
                    throw new RuntimeException('Offer with id ' . $productId . ' not found');
                }

                $preparedItems[$productId] = [
                    'NAME' => $productElement['NAME'],
                    //'BASE_PRICE' => $shipmentItem['price'],
                    'PRICE' => $shipmentItem['finalPrice'],
                    //'CUSTOM_PRICE' => 'Y',
                    'CURRENCY' => $this->getCurrencyCode(),
                    'LID' => Main\Context::getCurrent()->getSite(),
                    'QUANTITY' => $shipmentItem['quantity'],
                    'PRODUCT_PROVIDER_CLASS' => ProductProvider::class,
                    'PRODUCT_XML_ID' => $productElement['XML_ID'],
                    'CATALOG_XML_ID' => self::CATALOG_XML_ID,
                ];
            }
        }

        $basket = Sale\Basket::create($this->getSiteId());
        $basket->setFUserId(self::GOODS_USER_ID);
        foreach ($preparedItems as $preparedItemId => $preparedItem) {
            $basketItem = $basket->createItem('catalog', $preparedItemId);
            $basketItem->setFields($preparedItem);
        }

        $saveResult = $basket->save();
        if(!$saveResult->isSuccess()) {
            throw new RuntimeException('Basket cannot be saved: ' . current($saveResult->getErrorMessages()));
        }

        $order = Sale\Order::create($this->getSiteId(), self::GOODS_USER_ID);
        $order->setPersonTypeId(1);
        $order->setField('CURRENCY', $this->getCurrencyCode());
        $order->setField('STATUS_ID', self::DEFAULT_STATUS);
        $order->setField('USER_DESCRIPTION', 'Goods ' . $shipment['shipmentId']);
        $order->setBasket($basket);

        $shipmentCollection = $order->getShipmentCollection();
        $orderShipment = $shipmentCollection->createItem();
        $shipmentService = Sale\Delivery\Services\Manager::getById(self::DEFAULT_DELIVERY_ID);
        $orderShipment->setFields(array(
            'DELIVERY_ID' => $shipmentService['ID'],
            'DELIVERY_NAME' => $shipmentService['NAME'],
        ));
        $shipmentItemCollection = $orderShipment->getShipmentItemCollection();
        foreach ($basket as $basketItem) {
            $shipmentItem = $shipmentItemCollection->createItem($basketItem);
            $shipmentItem->setQuantity($basketItem->getQuantity());
        }

        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem();
        $paySystemService = Sale\PaySystem\Manager::getObjectById(self::DEFAULT_PAYMENT_SYSTEM_ID);
        $payment->setFields(array(
            'PAY_SYSTEM_ID' => $paySystemService->getField('PAY_SYSTEM_ID'),
            'PAY_SYSTEM_NAME' => $paySystemService->getField('NAME'),
        ));

        $propertyCollection = $order->getPropertyCollection();
        $phoneProp = $propertyCollection->getPhone();
        $phoneProp->setValue(self::DEFAULT_PHONE_NUMBER);
        $locationProp = $propertyCollection->getAttribute('IS_LOCATION');
        $locationProp->setValue(self::DEFAULT_CITY);

        $goodsOrderNumberProp = null;
        foreach ($propertyCollection as $propertyItem) {
            switch ($propertyItem->getField('CODE')) {
                case 'GOODS_ORDER_NUMBER';
                    $propertyItem->setValue($shipment['shipmentId']);
                    break;
                case 'DELIVERY_DATE_DEDUCTED':
                    $date = new \DateTime($shipment['shipmentDateFrom']);
                    $propertyItem->setValue($date->format('d.m.Y'));
                    break;
            }
        }

        $order->doFinalAction(true);
        $saveResult = $order->save();
        if(!$saveResult->isSuccess()) {
            throw new RuntimeException('Order cannot be saved: ' . current($saveResult->getErrorMessages()));
        }

        return $order->getId();
    }

    /**
     * @param array $orderData
     * @return array
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\NotImplementedException
     * @throws Main\NotSupportedException
     * @throws Main\ObjectException
     * @throws Main\ObjectNotFoundException
     * @throws Main\SystemException
     */
    public function addNewOrder($orderData)
    {
        $orderIds = [];

        if(empty($orderData)) {
            throw new RuntimeException('Order data is empty');
        }

        if(empty($orderData['shipments']) || !is_array($orderData['shipments'])) {
           throw new RuntimeException('Shipments are empty or broken');
        }

        foreach ($orderData['shipments'] as $shipment) {
            try {
                $orderId = $this->addSingleOrder($shipment);
                if(!empty($orderId)) {
                    $orderIds[$shipment['shipmentId']] = $orderId;
                }
            } catch (\Exception $e) {
                \AddMessage2Log($e->getMessage() . ' ' . json_encode($shipment), 'local.main.goods');
            }
        }

        return $orderIds;
    }

    public function searchNewOrders() {
        foreach ($this->api as $apiClient) {
            try {
                $this->searchNewOrdersApi($apiClient);
            } catch (\Exception $e) {
                \AddMessage2Log($e->getMessage(), 'local.main.goods');
            }
        }
    }

    /**
     * @param ApiClient $api
     */
    protected function searchNewOrdersApi($api)
    {
        $yesterday = strtotime('yesterday');
        $today = time();

        $newOrdersIds = $api->orderSearch([
            'dateFrom' => date('Y-m-d', $yesterday) . 'T23:50:00+03:00',
            'dateTo' => date('Y-m-d', $today) . 'T23:59:59+03:00',
            'statuses' => [
                'NEW'
            ],
            'count' => 5000
        ]);

        if(!empty($newOrdersIds)) {
            $newOrdersData = $api->orderGet($newOrdersIds);
            if(!empty($newOrdersData)) {
                try {
                    $this->addNewOrder($newOrdersData);
                } catch (\Exception $e) {
                    \AddMessage2Log($e->getMessage() . ' ' . json_encode($newOrdersData), 'local.main.goods');
                }
            }
        }
    }
}
