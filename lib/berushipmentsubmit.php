<?php

namespace Instrum\Main;

use Bitrix\Main;
use Bitrix\Sale;
use Yandex\Beru;
use Yandex\Beru\Admin\ShipmentSubmit;

class BeruShipmentSubmit extends ShipmentSubmit
{
    /** @var Beru\Api\Model\Order */
    protected $externalOrder;
    /** @var Beru\Entity\Sale\Order */
    protected $orderEntity;
    /** @var Beru\Admin\Assets */
    protected $assets;

    protected $siteId;
    protected $externalOrderId;
    protected $options;

    public function setExternalOrderId($externalOrderId)
    {
        $this->externalOrderId = $externalOrderId;
    }

    public function getExternalOrderId()
    {
        return (string)$this->externalOrderId;
    }

    public function setExternalOrder(Beru\Api\Model\Order $order)
    {
        $this->externalOrder = $order;
    }

    public function getExternalOrder()
    {
        return $this->externalOrder;
    }

    public function getOrderEntity()
    {
        return $this->orderEntity;
    }

    /**
     * @param Beru\Entity\Sale\Order $order
     */
    public function setOrderEntity(Beru\Entity\Sale\Order $order)
    {
        $this->orderEntity = $order;
    }

    /**
     * @return Beru\Api\Model\Order\ShipmentCollection|null
     */
    protected function getShipments()
    {
        $externalOrder = $this->getExternalOrder();
        $delivery = $externalOrder->getDelivery();

        return $delivery !== null ? $delivery->getShipments() : null;
    }

    /**
     * @param Beru\Api\Model\Order\Box $box
     * @return array
     */
    protected function getBoxProperties(Beru\Api\Model\Order\Box $box)
    {
        return array_merge(
            $this->getBoxCommonProperties($box),
            $this->getBoxSizeProperties($box),
            $this->getBoxWeightProperties($box)
        );
    }

    /**
     * @param Beru\Api\Model\Order\Box $box
     * @return array
     */
    protected function getBoxCommonProperties(Beru\Api\Model\Order\Box $box)
    {
        $boxId = (string)$box->getId();

        return [
            [
                'TITLE' => Beru\Config::getLang('ADMIN_ORDER_VIEW_SHIPMENT_BOX_ID'),
                'VALUE' => $boxId,
                'NAME' => 'ID',
                'HIDDEN' => $boxId === ''
            ]
        ];
    }

    /**
     * @param Beru\Api\Model\Order\Box $box
     * @return array
     */
    protected function getBoxSizeProperties(Beru\Api\Model\Order\Box $box)
    {
        $values = [
            $box->getWidth(),
            $box->getHeight(),
            $box->getDepth()
        ];
        $values = array_filter(
            $values,
            static function ($value) {
                return $value !== null;
            }
        );
        $exportValue = implode('x', $values);
        $unit = $box->getSizeUnit();

        return [
            [
                'TITLE' => Beru\Config::getLang('ADMIN_ORDER_VIEW_SHIPMENT_BOX_SIZE'),
                'VALUE' => $exportValue,
                'UNIT' => Beru\Data\Size::getUnitShortTitle($unit),
                'NAME' => 'SIZE',
                'HIDDEN' => empty($values)
            ]
        ];
    }

    /**
     * @param Beru\Api\Model\Order\Box $box
     * @return array
     */
    protected function getBoxWeightProperties(Beru\Api\Model\Order\Box $box)
    {
        $weight = $box->getWeight();
        $unit = $box->getWeightUnit();

        return [
            [
                'TITLE' => Beru\Config::getLang('ADMIN_ORDER_VIEW_SHIPMENT_BOX_WEIGHT'),
                'VALUE' => $weight,
                'UNIT' => Beru\Data\Weight::getUnitShortTitle($unit),
                'NAME' => 'WEIGHT',
                'HIDDEN' => $weight === null
            ]
        ];
    }

    /**
     * @param Beru\Api\Model\Order\Box $box
     * @return array
     */
    protected function getBoxSizeValues(Beru\Api\Model\Order\Box $box)
    {
        $sizeUnit = $box->getSizeUnit();
        $sizeUnitTitle = Beru\Data\Size::getUnitShortTitle($sizeUnit);
        $weightUnit = $box->getWeightUnit();
        $weightUnitTitle = Beru\Data\Weight::getUnitShortTitle($weightUnit);

        return [
            'ID' => [
                'VALUE' => $box->getId(),
                'HIDDEN' => true,
            ],
            'WIDTH' => [
                'VALUE' => $box->getWidth(),
                'UNIT' => $sizeUnitTitle
            ],
            'HEIGHT' => [
                'VALUE' => $box->getHeight(),
                'UNIT' => $sizeUnitTitle
            ],
            'DEPTH' => [
                'VALUE' => $box->getDepth(),
                'UNIT' => $sizeUnitTitle
            ],
            'WEIGHT' => [
                'VALUE' => $box->getWeight(),
                'UNIT' => $weightUnitTitle
            ],
        ];
    }

    /**
     * @param string $itemId
     * @return Beru\Api\Model\Order\Item|null
     * */
    protected function getOrderItem($itemId)
    {
        $order = $this->getExternalOrder();
        $items = $order->getItems();

        return $items !== null ? $items->getItemById($itemId) : null;
    }

    protected function getItemBasketData($offerId)
    {
        $orderEntity = $this->getOrderEntity();
        $basketCode = $orderEntity->getBasketItemCode($offerId);
        $result = null;

        if ($basketCode !== null) {
            $basketResult = $orderEntity->getBasketItemData($basketCode);

            if ($basketResult->isSuccess()) {
                $result = $basketResult->getData();
            }
        }

        return $result;
    }

    /**
     * @param $offerId
     * @param $basketData
     * @return string|null
     */
    protected function getItemName($offerId, $basketData)
    {
        if (!empty($basketData['NAME'])) {
            $result = $basketData['NAME'];
        } else {
            $result = Beru\Config::getLang('ADMIN_ORDER_VIEW_BASKET_OFFER_NAME', [
                    '#ID#' => $offerId
            ]);
        }

        return $result;
    }

    /**
     * @return Beru\Entity\Sale\Order
     * @throws Main\SystemException
     */
    public function loadOrder()
    {
        $orderEntity = Beru\Entity\Manager::getOrder();
        $loadResult = $orderEntity->loadExternal($this->externalOrderId, $this->getTradingPlatform());

        if (!$loadResult->isSuccess()) {
            throw new Main\SystemException(
                Beru\Config::getLang('ADMIN_ORDER_VIEW_CANT_LOAD_BITRIX_ORDER', [
                    '#MESSAGE#' => implode(PHP_EOL, $loadResult->getErrorMessages())
                ])
            );
        }

        return $orderEntity;
    }

    public function makeData()
    {
        $result = [];
        $shipments = $this->getShipments();

        //$isAllowFill = Beru\Admin\Access::isWriteAllowed() && $this->getExternalOrder()->isShipmentEditAllowed();
        $isAllowFill = $this->getExternalOrder()->isShipmentEditAllowed();
        if (!$isAllowFill) {
            return false;
        }

        $isFewShipments = (count($shipments) > 1);
        $shipmentIndex = 0;
        $orderCurrency = $this->getExternalOrder()->getCurrency();
        $isOrderConfirmed = $this->getExternalOrder()->isConfirmed();
        //$baseInputName = 'YABERU_SHIPMENT';
        $boxNumber = 0;

        /** @var Beru\Api\Model\Order\Shipment $shipment */
        foreach ($shipments as $shipment) {
            $boxCollection = $shipment->hasSavedBoxes() ? $shipment->getSavedBoxes() : $shipment->getBoxes();
            $boxes = $boxCollection;
            $isBoxesEmpty = false;
            $boxIndex = 0;
            $isAllowShipmentPrint = $this->getExternalOrder()->isProcessing() && $shipment->hasSavedBoxes();

            if (count($boxes) === 0) {
                $isBoxesEmpty = true;
                $boxes = [new Beru\Api\Model\Order\Box()];
            }

            if ($isAllowShipmentPrint) {
                //$isAllowPrint = true;
            }

            $result['SITE_ID'] = $this->getOrderEntity()->getSiteId();
            $result['ORDER_ID'] = $this->getOrderEntity()->getId();
            $result['ORDER_ACCOUNT_NUMBER'] = $this->getOrderEntity()->getAccountNumber();
            $result['EXTERNAL_SHIPMENT_ID'] = $shipment->getId();
            $result['EXTERNAL_ORDER_ID'] = $this->getExternalOrder()->getId();

            /** @var Beru\Api\Model\Order\Box $box */
            foreach ($boxes as $box) {
                ++$boxNumber;

                //$boxInputName = $shipmentInputName . '[BOX][' . $boxIndex . ']';
                //$boxProperties = $this->getBoxProperties($box);
                $boxSizeValues = $this->getBoxSizeValues($box);
                $boxItems = $box->getItems();
                $isEmptyBoxItems = (count($boxItems) === 0);

                if (count($boxItems) === 0) {
                    $isEmptyBoxItems = true;
                    $boxItems = [new Beru\Api\Model\Order\BoxItem()];
                }
                //$result['BOX'][$boxIndex] = [];
                $boxArr = [];

                $boxArr['SAVED_ID'] = $box->getSavedId();
                $boxArr['NUMBER'] = $boxNumber;

                foreach ($boxSizeValues as $sizeName => $boxSize) {
                    if (!$isBoxesEmpty) {
                        $boxArr['SIZE'][$sizeName] = $boxSize['VALUE'];
                    }
                }

                $boxItemIndex = 0;

                foreach ($boxItems as $boxItem) {
                    $boxItemId = $boxItem->getId();
                    $orderItem = $boxItemId !== null ? $this->getOrderItem($boxItemId) : null;
                    $orderItemName = null;
                    $basketData = null;
                    $boxItemPrice = null;
                    //$boxItemPriceFormatted = '&mdash;';
                    //$boxItemInputName = $boxInputName . '[ITEM][' . $boxItemIndex . ']';

                    if ($orderItem !== null) {
                        $basketData = $this->getItemBasketData($orderItem->getOfferId());
                        $boxItemPrice = $orderItem->getFullPrice();
                        //$boxItemPriceFormatted = Beru\Data\Currency::format($boxItemPrice, $orderCurrency);
                        $orderItemName = $orderItem->getOfferName();

                        if ($orderItemName === '') {
                            //$orderItemName = $this->getItemName($orderItem->getOfferId(), $basketData);
                        }
                    }

                    if (!$isEmptyBoxItems) {
                        $boxArr['ITEM'][$boxItemIndex]['ID'] = $boxItem->getId();
                    }
                    $boxArr['ITEM'][$boxItemIndex]['SAVED_ID'] = $boxItem->getSavedId();

                    if (!$isEmptyBoxItems) {
                        $boxArr['ITEM'][$boxItemIndex]['COUNT'] = $boxItem->getCount();
                    }
                    ++$boxItemIndex;
                }

                $result['BOX'][$boxIndex] = $boxArr;
            }

            break; //TODO
            //++$boxIndex;
        }
        return $result;
    }


    /**
     * @return Beru\TradingPlatform\Options
     * @throws Main\ArgumentNullException
     */
    protected function getOptions()
    {
        if ($this->options === null) {
            $this->options = $this->loadOptions();
        }

        return $this->options;
    }

    /**
     * @return Beru\TradingPlatform\Options
     * @throws Main\ArgumentNullException
     */
    protected function loadOptions()
    {
        $tradingPlatform = $this->getTradingPlatform();
        return $tradingPlatform->getOptions($this->siteId);
    }

    /**
     * @return Beru\TradingPlatform\Platform
     * @throws Main\ArgumentNullException
     */
    protected function getTradingPlatform()
    {
        return Beru\TradingPlatform\Platform::getObject();
    }

    /**
     * @param $orderId
     * @param Beru\TradingPlatform\Platform $platform
     * @return bool|mixed
     * @throws Main\ArgumentException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public function searchExternalOrderId($orderId, $platform)
    {
        $result = false;

        $select = ['ORDER_ID', 'EXTERNAL_ORDER_ID'];

        $query = Sale\TradingPlatform\OrderTable::getList(
            [
                'filter' => [
                    '=TRADING_PLATFORM_ID' => $platform->getId(),
                    //'=EXTERNAL_ORDER_ID' => $externalIds
                    '=ORDER_ID' => $orderId
                ],
                'select' => $select
            ]
        );

        if ($row = $query->fetch()) {
            $result = $row['EXTERNAL_ORDER_ID'];
        }
        return $result;
    }

    /**
     * @param Beru\Api\Model\Order $externalOrder
     * @param Beru\Entity\Sale\Order $orderEntity
     * @return array|bool
     */
    public function prepareShipmentData($externalOrder, $orderEntity)
    {
        $this->setExternalOrder($externalOrder);
        $this->setOrderEntity($orderEntity);

        return $this->makeData();
    }

    /**
     * @param $orderId
     * @param $siteId
     * @return array
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ObjectPropertyException
     * @throws Main\SystemException
     */
    public function sendBeruShipment($orderId, $siteId)
    {
        $platform = Beru\TradingPlatform\Platform::getObject();

        $externalOrderId = $this->searchExternalOrderId($orderId, $platform);
        if (!$externalOrderId) {
            throw new Main\SystemException('EXTERNAL_ORRER_NOT_FOUND');
        }
        $this->setExternalOrderId($externalOrderId);

        $viewController = new Beru\Admin\OrderView();

        $viewController->setExternalOrderId($externalOrderId);
        $viewController->setSiteId($siteId);
        //$viewController->checkReadAccess();
        $viewController->checkParameters();
        $viewController->loadModules();

        $options = $platform->getOptions($siteId);
        $externalOrder = Beru\Api\Model\Order::fetch($options, $externalOrderId);

        try {
            $orderEntity = $this->loadOrder();

            $requestShipment = $this->prepareShipmentData($externalOrder, $orderEntity);

            $shipmentForSave = $this->fillShipmentForSave($requestShipment);
            $shipmentForSubmit = $this->extendShipmentForSubmit($requestShipment, $shipmentForSave);

            $this->saveShipment($shipmentForSave);
            $this->submitBoxes($shipmentForSubmit);
            $this->flushCache();
        } catch (Main\SystemException $exception) {
            throw new Main\SystemException($exception->getMessage());
        }

        return [
            'status' => 'ok',
            'message' => Beru\Config::getLang('ADMIN_SHIPMENT_SUBMIT_SUCCESS_SEND')
        ];
    }

    protected function saveShipment($shipmentForSave)
    {
        if ($this->isExistSavedShipment($shipmentForSave)) {
            $shipmentId = $shipmentForSave['ID'];
            unset($shipmentForSave['ID']);

            Beru\Api\Internals\ShipmentTable::update($shipmentId, $shipmentForSave);
        } else {
            Beru\Api\Internals\ShipmentTable::add($shipmentForSave);
        }
    }

    protected function isExistSavedShipment($shipmentForSave)
    {
        $query = Beru\Api\Internals\ShipmentTable::getList(
            [
                'filter' => ['=ID' => $shipmentForSave['ID']],
                'select' => ['ID'],
                'limit' => 1
            ]
        );

        return $query->fetch();
    }

}
