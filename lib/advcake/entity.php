<?php

namespace Instrum\Main\AdvCake;

use Bitrix\Main\Loader;
use Bitrix\Sale;

abstract class Entity{

    private static $instances = [];

    protected $pageType;
    protected $user;

    /** @var Collection */
    protected $basket;

    public function __construct()
    {
        Loader::includeModule('iblock');
        //$this->getUser();
        //$this->getBasket();

        self::$instances[] = $this;
    }

    protected static function extendSectionProducts(&$arProducts)
    {
        $arProductsId = array_keys($arProducts);
        $arItemSections = [];
        $arSections = [];

        if(!$arProductsId) return false;

        $rs = \CIBlockElement::GetList([], ['ID' => $arProductsId], false, false, ['ID', 'IBLOCK_SECTION_ID']);
        while($arItem = $rs->GetNext())
        {
            $arItemSections[$arItem['ID']] = $arItem['IBLOCK_SECTION_ID'];
        }

        if(!$arItemSections) return false;

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

        return true;
    }

    protected static function getBasketItems()
    {
        $basket = Sale\Basket::loadItemsForFUser(Sale\Fuser::getId(), \Bitrix\Main\Context::getCurrent()->getSite());
        return $basket->getBasketItems();
    }

    protected static function getBasketItemsByOrder($orderId)
    {
        $basket = \Bitrix\Sale\Order::load($orderId)->getBasket();
        return $basket->getBasketItems();
    }

    protected static function getBasket($order = false)
    {
        $collection = new Collection();
        $arProducts = [];
        /** @var Sale\BasketItem $item */

        $basketItems = $order ? self::getBasketItemsByOrder($order) : self::getBasketItems();

        foreach($basketItems as $item)
        {
            $arProducts[$item->getProductId()] = [
                'id' => $item->getProductId(),
                'name' => $item->getField('NAME'),
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }
        self::extendSectionProducts($arProducts);

        foreach($arProducts as $arProduct)
        {
            $collection->addItem(
                $arProduct['id'],
                $arProduct['name'],
                $arProduct['price'],
                $arProduct['quantity'],
                $arProduct['categoryId'],
                $arProduct['categoryName']
            );
        }

        if(!$collection->isEmpty()) return $collection;
    }

    protected static function getUser()
    {
        global $USER;
        if($email = $USER->GetEmail())
        {
            return md5($email);
        }
    }

    public function show()
    {
        /*
        // $jsBuffer = "window.itorg_advcake.ready()";
        $this->get($jsBuffer);
        if($jsBuffer)
            echo '<script>'.$jsBuffer.'</script>';
        */
    }

    public function get(&$jsBuffer)
    {
        if($this->pageType)
        {
            $jsBuffer .= ".setPageType(".$this->pageType.")";
        }

        if($this->user)
        {
            $jsBuffer .= ".setUser('".$this->user."')";
        }

        if($this->basket)
        {
            $jsBuffer .= ".setBasketProducts(".$this->basket->packToJS().")";
        }

        return true;
    }

    public final static function getAll()
    {

        $basket = self::getBasket();
        $data = '{user:"'.self::getUser().'", basket: '.($basket ? $basket->packToJS() : 'null').'}';
        // echo '<script>window.itorg_advcake.setDinamicData('.$data.')</script>';
    }

    public static function getString($text)
    {
        return "\"".addslashes($text)."\"";
    }

    public static function bindItemsList($arResult)
    {
        $products = new Collection();

        $arItemsSections = [];
        $arSections = [];

        $arItems = $arResult['ITEMS_MIN'] ? $arResult['ITEMS_MIN'] : $arResult['ITEMS'];
        if(!$arItems) return false;

        foreach($arItems as $arItem)
        {
            $arItemsSections[] = $arItem['IBLOCK_SECTION_ID'];
        }

        $rs = \CIBlockSection::GetList([], ["ID" => $arItemsSections]);
        while($arSection = $rs->GetNext())
        {
            $arSections[$arSection['ID']] = $arSection;
        }

        foreach($arItems as $arItem)
        {
            $products->addItem(
                $arItem['ID'],
                $arItem['NAME'],
                $arItem["PRICE"] ? $arItem["PRICE"] : $arItem["MIN_PRICE"]["VALUE_VAT"],
                1,
                $arItem['IBLOCK_SECTION_ID'],
                $arSections[$arItem['IBLOCK_SECTION_ID']]["NAME"]
            );
        }

        (new Items())->setProducts($products)->show();
    }

    public static function bindSectionList($arResult)
    {
        $products = new Collection();
        $arItems = $arResult['ITEMS_MIN'] ? $arResult['ITEMS_MIN'] : $arResult['ITEMS'];
        if(!$arItems) return false;

        foreach($arItems as $arItem)
        {
            $products->addItem(
                $arItem['ID'],
                $arItem['NAME'],
                $arItem["PRICE"] ? $arItem["PRICE"] : $arItem["MIN_PRICE"]["VALUE_VAT"]
            );
        }

        (new Section())
            ->setCategory($arResult['ID'], $arResult['NAME'])
            ->setProducts($products)
            ->show();
    }
}