<?php

namespace Instrum\Main\Catalog;

use Bitrix\Main\Loader;
use Bitrix\Sale;
use Bitrix\Main;
use Bitrix\Currency;
use Instrum\Ajax\Controller;
use Sotbit\Regions\Location\Domain;

Loader::includeModule('sale');
Loader::includeModule('catalog');

class Basket
{
    public $arErrors = [];
    public $basket;

    /**
     * Basket constructor.
     * @throws Main\SystemException
     */
    public function __construct()
    {
        $this->basket = $this->get();
        $this->app = Main\Application::getConnection();
        $this->request = Main\Application::getInstance()->getContext()->getRequest();
    }

    /**
     * @throws \Exception
     */
    public function doAction()
    {
        $basket = $this->get();
        $arItems = $basket->getOrderableItems();

        if(isset($_SESSION['remote_items']['NOT_AVAILABLE']) && !empty($_SESSION['remote_items']['NOT_AVAILABLE'])){
            $arProducts = [];
            $rsProducts = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => CATALOG_IB,
                    'PROPERTY_CML2_ARTICLE' => $_SESSION['remote_items']['NOT_AVAILABLE']
                ],
                false,false,
                ['ID', 'PROPERTY_CML2_ARTICLE']
            );
            while($result = $rsProducts->Fetch()) {
                if($_REQUEST['params']['action'] == 'delete' && $result['ID'] == $_REQUEST['params']['id']) {
                    unset($_SESSION['remote_items']['NOT_AVAILABLE'][$result['PROPERTY_CML2_ARTICLE_VALUE']]);
                }
            }
        }


        foreach($arItems as $item) {
            if($item->getId() == $_REQUEST['params']['id']) {
                switch ($_REQUEST['params']['action']){
                    case 'delete':
                        \CSaleBasket::Delete($item->getId());
                        // $item->delete();                          
                        break;
                    case 'changeQuantity':
                        $item->setField('QUANTITY', intval($_REQUEST['params']['qty']));
                        break;
                }
                // $rs = $item->save();
                // if(!$rs->isSuccess()){
                //     foreach($rs->getErrors() as $error){
                //         $this->arErrors[] = $error->getMessage();
                //     }

                // }
            }
        }

        $basket->save();

        if(!empty($this->arErrors)) {
            throw new \Exception('не удалось сохранить корзину. ' . implode(PHP_EOL, $this->arErrors));
        }else{
            Controller::response(['success' => true]);
        }
    }

    /**
     *
     */
    public function removeAllRemoteAction()
    {
        unset($_SESSION['remote_items']['NOT_AVAILABLE']);
        Controller::response(['success' => true]);
    }

    /**
     *
     */
    public function removeCouponAction(){
        \CCatalogDiscountCoupon::ClearCoupon();
        Controller::response(['success' => true]);
    }

    /**
     *
     */
    public function addCouponAction()
    {
        \CCatalogDiscountCoupon::SetCoupon($_REQUEST['coupon']);
        Controller::response(['success' => true]);
    }

    /**
     * @return Sale\BasketBase
     */
    public function get()
    {
        return Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Main\Context::getCurrent()->getSite());
    }

    /**
     * @return Sale\BasketBase
     */
    public function getItems()
    {
        return $this->basket->getOrderableItems();
    }

    /**
     *
     */
    public function checkNotAvailableItems()
    {
        $basketItems = $this->get()->getOrderableItems();
        foreach($basketItems as $item) {
            $productData = \CCatalogProduct::GetByID($item->getProductId());
            if($productData['QUANTITY'] <= 0) {
                \CSaleBasket::Delete($item->getId());
            }
        }
    }

    public function getCurrentRegionStoreIds()
    {
        if (!empty($_SESSION['SOTBIT_REGIONS']['STORE'])) {
            $result = $_SESSION['SOTBIT_REGIONS']['STORE'];
        } else {
            $domain = new Domain();
            $result = $_SESSION['SOTBIT_REGIONS']['STORE'];
        }
        return $result;
    }

    /**
     * @return bool
     * @throws Main\ArgumentException
     * @throws Main\ArgumentNullException
     * @throws Main\ArgumentOutOfRangeException
     * @throws Main\ArgumentTypeException
     * @throws Main\Db\SqlQueryException
     * @throws Main\NotImplementedException
     * @throws Main\NotSupportedException
     */
    public function obtainRemoteBasket()
    {
        if (!$this->request->isPost()) {
            return false;
        }

        $entityBody = $this->request->toArray()['REMOTE_BASKET_ITEMS'];
        if(!$entityBody){
            return false;
        }

        $oBasket = $this->get();

        $arItems = array_filter(explode(';', $entityBody));
        $basket = [];
        foreach($arItems as &$item){
            list($artNumber, $quantity) = explode('::', $item);
            $basket[$artNumber] = $quantity;
        }

        $curRegionStoreIds = $this->getCurrentRegionStoreIds();
        if (empty($curRegionStoreIds)){
            throw new Main\SystemException('STORE_NOT_FOUND');
        }

        $rs = \CIBlockElement::GetList(
            ['ID' => 'DESC'],
            [
                'IBLOCK_ID' => CATALOG_IB,
                'PROPERTY_CML2_ARTICLE' => array_keys($basket),
            ],
            false,false,
            [
                'NAME', 'ID', 'IBLOCK_ID', 'XML_ID',
                'PROPERTY_CML2_ARTICLE',
                //'CATALOG_QUANTITY'
            ]
        );

        $basketItems = [];
        while ($data = $rs->Fetch()) {
            $basketItems[$data['ID']] = $data;
        }

        if (count($basketItems)) {
            $regionAvailable = [];
            $rsStore = \CCatalogStoreProduct::GetList(
                [],
                [
                    'PRODUCT_ID' => array_keys($basketItems),
                    'STORE_ID' => $curRegionStoreIds,
                ],
                false,
                false,
                [
                    'STORE_ID',
                    'PRODUCT_ID',
                    'AMOUNT'
                ]
            );

            while ($row = $rsStore->Fetch()) {

                if(!isset($regionAvailable[$row['PRODUCT_ID']])) {
                    $regionAvailable[$row['PRODUCT_ID']] = 0;
                }
                if(!empty($row['AMOUNT'])) {
                    $regionAvailable[$row['PRODUCT_ID']] = max($row['AMOUNT'], $regionAvailable[$row['PRODUCT_ID']]);
                }
            }

            $arFound = [];
            foreach ($basketItems as $productId => $result) {
                $canBuy = true;
                $arFound[] = $result['PROPERTY_CML2_ARTICLE_VALUE'];
                $quantity = $basket[$result['PROPERTY_CML2_ARTICLE_VALUE']];

                if($quantity > $regionAvailable[$productId] && $regionAvailable[$productId] > 0) {
                    $this->arErrors['LOW_QTY'][$result['PROPERTY_CML2_ARTICLE_VALUE']] = $quantity;
                    $_SESSION['remote_items']['LOW_QTY'][$result['PROPERTY_CML2_ARTICLE_VALUE']] = $quantity;
                    $quantity = $regionAvailable[$productId];
                } elseif ($regionAvailable[$productId] <= 0) {
                    $this->arErrors['NOT_AVAILABLE'][] = $result['PROPERTY_CML2_ARTICLE_VALUE'];
                    $_SESSION['remote_items']['NOT_AVAILABLE'][$result['ARTNUMBER']] = $result['PROPERTY_CML2_ARTICLE_VALUE'];
                    $canBuy = false;
                }

                if($canBuy) {
                    $propertyList = [];

                    $iBlockXmlID = (string)\CIBlock::GetArrayByID(CATALOG_IB, 'XML_ID');
                    if ($iBlockXmlID !== '')
                    {
                        $fields['CATALOG_XML_ID'] = $iBlockXmlID;
                        $propertyData = array(
                            'NAME' => 'Catalog XML_ID',
                            'CODE' => 'CATALOG.XML_ID',
                            'VALUE' => $iBlockXmlID
                        );
                        $propertyList[] = $propertyData;
                        unset($propertyData);
                    }
                    unset($iBlockXmlID);
                    $propertyData = array(
                        'NAME' => 'Product XML_ID',
                        'CODE' => 'PRODUCT.XML_ID',
                        'VALUE' => $result['XML_ID']
                    );
                    $propertyList[] = $propertyData;
                    unset($propertyData);
                    if ($item = $oBasket->getExistsItem('catalog', $productId, $propertyList)) {
                        $item->setField('QUANTITY', $quantity);
                    } else {
                        $item = $oBasket->createItem('catalog', $productId);
                        $item->setFields(array(
                            'QUANTITY' => $quantity,
                            'CURRENCY' => Currency\CurrencyManager::getBaseCurrency(),
                            'LID' => Main\Context::getCurrent()->getSite(),
                            'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
                        ));
                    }

                    $r = $oBasket->save();
                    if (!$r->isSuccess()) {
                        $this->arErrors[] = current($r->getErrorMessages());
                    }
                }
            }
        }

        $arLost = array_diff(array_keys($basket), $arFound);
        if(count($arLost) > 0){
            $this->arErrors[] = 'не найдены артикулы: ' . implode(', ', $arLost);
        }
        if((count($this->arErrors['NOT_AVAILABLE']) + count($arLost)) >= count($basket)) {
            $this->arErrors['TOTAL_CANT_BUY'][] = true;
            $_SESSION['remote_items']['TOTAL_CANT_BUY'][] = true;
        }
        // if(!empty($this->arErrors)){            
        //     $errorMsg = '';
        //     foreach($this->arErrors as $error){
        //         if(!is_array($error)){                    
        //             // echo '<p style="color:red">'.$error.'</p>';
        //         }
        //     }            
        // }else{
        LocalRedirect('/personal/cart/');
        // }
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->arErrors;
    }

    /**
     * @return array|null
     */
    public static function getBasketIds()
    {
        static $basketIds = null;
        if(!isset($basketIds)) {
            $basketItems = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Main\Context::getCurrent()->getSite())->getBasketItems();
            foreach($basketItems as $item) {
                $basketIds[] = $item->getProductId();
            }
        }
        return $basketIds;
    }

    public static function getCurrentBasketStats()
    {
        static $stats = null;
        if(!isset($stats)) {
            $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), Main\Context::getCurrent()->getSite());
            $stats = [
                'sum' => round($basket->getPrice()),
                'count' => (int) array_sum($basket->getQuantityList()),
            ];
        }
        return $stats;
    }
}