<?php


namespace Instrum\Main\Goods;

use \CCatalogProductProvider;
use \Bitrix\Main\Loader;
use \CPrice;

Loader::includeModule('catalog');
Loader::includeModule('sale');

class ProductProvider extends CCatalogProductProvider
{
    const PRICE_TYPE_ID = 6;

    public static function GetProductData($params)
    {
        $result = parent::GetProductData($params);

        $rowset = CPrice::GetList([], [
            'PRODUCT_ID' => $params['PRODUCT_ID'],
            'CATALOG_GROUP_ID' => self::PRICE_TYPE_ID
        ]);
        if($price = $rowset->Fetch()) {
            $result['PRODUCT_PRICE_ID'] = $price['ID'];
            $result['PRICE_TYPE_ID'] = $price['CATALOG_GROUP_ID'];
            $result['PRICE'] = (float) $price['PRICE'];
            $result['BASE_PRICE'] = (float) $price['PRICE'];
            $result['NOTES'] = $price['CATALOG_GROUP_NAME'];
        }

        return $result;
    }
}