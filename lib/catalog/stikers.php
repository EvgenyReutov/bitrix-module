<?php

namespace Instrum\Main\Catalog;

use Instrum\Main\Catalog\Product;
use Instrum\Main\Catalog\Sticker\Collection;
use Instrum\Main\Iblock\Element;


class Stikers
{
    /** @var string Новинки */
    const BASE_NEW = "new";
    /** @var string Хиты продаж */
    const BASE_BSELLER = "bestseller";
    /** @var string Лучшие предложения */
    const BASE_BDEAL = "best_deal";

    private $IBLOCK_CODE = 'stickers';
    private $productID;

    public static $inherited_classes = [
        'new' => ['PROPERTY' => 'NOVINKA', 'LABEL' => 'New'],
        'bestseller' => ['PROPERTY' => 'KHIT_PRODAZH', 'LABEL' => 'Хит'],
        'best_deal' => ['PROPERTY' => 'LUCHSHEE_PREDLOZHENIE', 'LABEL' => '']
    ];

    protected static $priority = ['discount', 'black-friday', 'new', 'bestseller'];

    public function __construct($productID = false)
    {
        $this->productID = $productID;
    }

    public function getCollection($exclude = ['best_deal']){

        $list = [];
        $rs = \CIBlockElement::GetList([], ["IBLOCK_CODE" => $this->IBLOCK_CODE, "ACTIVE" => "Y", "ACTIVE_DATE" => "Y", "PROPERTY_PRODUCTS" => $this->productID]);
        while($element = $rs->GetNextElement())
        {
            $arElement = $element->GetFields();
            $arElement['PROPERTIES'] = $element->GetProperties();
            //фильтруем по регионам
            if ($arElement['PROPERTIES']['REGIONS']['VALUE'][0]) {
                $regionMatch = false;
                foreach ($arElement['PROPERTIES']['REGIONS']['VALUE'] as $regionId) {
                    if ($regionId && $regionId == $_SESSION['SOTBIT_REGIONS']['ID']) {
                        $regionMatch = true;
                        break;
                    }
                }
                if ($regionMatch == false)
                    continue;
            }

            $list[] = $arElement;
        }
        return new Collection($list, $exclude, self::$priority);
    }

    public function getProductsByClassName($className)
    {
        $products = [];

        if(in_array($className, array_keys(self::$inherited_classes)))
        {
            $products = $this->findFromCatalog(self::$inherited_classes[$className]['PROPERTY']);
        }

        $property = \CIBlockPropertyEnum::GetList([], ["IBLOCK_CODE" => $this->IBLOCK_CODE, "XML_ID" => $className])->Fetch();
        $rs = \CIBlockElement::GetList([], [
            "IBLOCK_CODE" => $this->IBLOCK_CODE,
            "PROPERTY_INHERIT_BASE" => $property['ID'],
            'ACTIVE' => 'Y',
            'ACTIVE_DATE' => 'Y'
        ]);

        while($element = $rs->GetNextElement())
        {
            $props = $element->GetProperties([], ['CODE' => 'PRODUCTS']);
            $products = array_merge($products, $props['PRODUCTS']['VALUE']);
        }

        return array_unique($products);
    }

    public function findFromCatalog($stickerProperty)
    {
        $products = [];

        $rs = \CIBlockElement::GetList([], [
            'IBLOCK_CODE' => (new Product())->IBLOCK_CODE,
            'PROPERTY_'.$stickerProperty.'_VALUE' => 'Да'
        ], false, false, ['ID', 'IBLOCK_ID']);

        while($element = $rs->GetNext())
        {
            $products[] = $element['ID'];
        }

        return $products;
    }
}