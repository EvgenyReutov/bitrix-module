<?php

namespace Instrum\Main\Cashbox;

use Bitrix\Sale\Cashbox\Check;
use Bitrix\Sale\Order;

class AdvancePaymentCheck extends Check
{
    /**
     * @return string
     */
    public static function getType()
    {
        $trace = debug_backtrace();
        if(
            !empty($trace) &&
            !empty($trace[1]) &&
            !empty($trace[1]['function']) &&
            $trace[1]['function'] == 'buildCheckQuery'
        ) {
            return 'fullprepayment';
        }
        return 'advancepayment';
    }

    /**
     * @return string
     */
    public static function getName()
    {
        return 'Аванс (Предоплата 100%)';
    }

    /**
     * @return string
     */
    public static function getCalculatedSign()
    {
        return static::CALCULATED_SIGN_INCOME;
    }

    /**
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ArgumentNullException
     * @throws \Bitrix\Main\ArgumentOutOfRangeException
     * @throws \Bitrix\Main\ArgumentTypeException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\NotImplementedException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function extractDataInternal()
    {
        $result = parent::extractDataInternal();

        if(empty($result['DELIVERY'])) {

            /** @var \Bitrix\Sale\Order $order */
            $order = $result['ORDER'];

            if($order) {
                $result['DELIVERY'] = [];

                $shipmentCollection = $order->getShipmentCollection();
                /** @var \Bitrix\Sale\Shipment $shipment */
                foreach ($shipmentCollection as $shipment) {
                    if(!$shipment->isSystem()) {
                        $priceDelivery = (float) $shipment->getPrice();
                        if($priceDelivery > 0) {
                            $item = array(
                                'ENTITY' => $shipment,
                                'NAME' => 'Доставка',
                                'BASE_PRICE' => (float)$shipment->getField('BASE_PRICE_DELIVERY'),
                                'PRICE' => $priceDelivery,
                                'SUM' => $priceDelivery,
                                'QUANTITY' => 1,
                                'VAT' => $this->getDeliveryVatId($shipment)
                            );

                            if ($shipment->isCustomPrice()) {
                                $item['BASE_PRICE'] = $priceDelivery;
                            } else {
                                $priceDeliveryDiscount = (float) $shipment->getField('DISCOUNT_PRICE');
                                if ($priceDeliveryDiscount != 0) {
                                    $item['DISCOUNT'] = [
                                        'PRICE' => $shipment->getField('DISCOUNT_PRICE'),
                                        'TYPE' => 'C',
                                    ];
                                }
                            }

                            $result['DELIVERY'][] = $item;
                        }
                    }
                }
            }
        }

        if(empty($result['PRODUCTS'])) {

            /** @var \Bitrix\Sale\Order $order */
            $order = $result['ORDER'];
            if($order) {
                $basket = $order->getBasket();
                if($basket) {
                    $result['PRODUCTS'] = [];

                    /** @var Sale\BasketItem $basketItem */
                    foreach ($basket->getBasketItems() as $basketItem) {
                        $item = array(
                            'ENTITY' => $basketItem,
                            'PRODUCT_ID' => $basketItem->getProductId(),
                            'NAME' => $basketItem->getField('NAME'),
                            'BASE_PRICE' => $basketItem->getBasePriceWithVat(),
                            'PRICE' => $basketItem->getPriceWithVat(),
                            'SUM' => $basketItem->getPriceWithVat() * $basketItem->getQuantity(),
                            'QUANTITY' => (float)$basketItem->getQuantity(),
                            'VAT' => $this->getProductVatId($basketItem)
                        );

                        if ($basketItem->isCustomPrice()) {
                            $item['BASE_PRICE'] = $basketItem->getPriceWithVat();
                        }
                        else {
                            if ((float) $basketItem->getDiscountPrice() != 0) {
                                $item['DISCOUNT'] = [
                                    'PRICE' => (float) $basketItem->getDiscountPrice(),
                                    'TYPE' => 'C',
                                ];
                            }
                        }

                        $result['PRODUCTS'][] = $item;
                    }
                }
            }
        }

        if(empty($result['PRODUCTS'])) {
            $result['PRODUCTS'] = array(
                array(
                    'NAME' => 'Внесение аванса',
                    'QUANTITY' => 1,
                    'PRICE' => $result['TOTAL_SUM'],
                    'SUM' => $result['TOTAL_SUM'],
                    'BASE_PRICE' => $result['TOTAL_SUM'],
                )
            );
        }

        return $result;
    }

    /**
     * @return string
     */
    public static function getSupportedRelatedEntityType()
    {
        return static::SUPPORTED_ENTITY_TYPE_NONE;
    }
}