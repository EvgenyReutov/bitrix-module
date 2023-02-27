<?php


namespace Instrum\Main\Listing;


use Bitrix\Main\DB\Connection;

class SortPropertiesService
{
    const PROPERTY_MARGIN_CODE = 'MARGIN';
    const PROPERTY_STM_CODE = 'IS_STM';
    const IBLOCK_FIELD_PREFIX = 'ITT_';

    const SELL_PRICE_ID = 3;
    const PURCHASE_PRICE_ID = 25;

    const IBLOCK_ID = 20;
    const BRANDS_IBLOCK_ID = 13;

    const PROPERTY_BRAND_ID = 12260;

    const PROPERTY_SORT_VALUE = 500;

    /** @var Connection */
    protected $db;

    public function __construct($db)
    {
        if (empty($db)) {
            throw new \RuntimeException('DB connection is not defined');
        }

        $this->db = $db;
    }

    protected function checkPropertyCreated($code, $name, $iblockId)
    {
        $property = \CIBlockProperty::GetList(
            [],
            [
                'CODE' => $code,
                'IBLOCK_ID' => $iblockId
            ]
        )->Fetch();
        if (empty($property)) {
            $property = new \CIBlockProperty;
            return $property->Add(
                [
                    'NAME' => $name,
                    'ACTIVE' => 'Y',
                    'SORT' => self::PROPERTY_SORT_VALUE,
                    'CODE' => $code,
                    'PROPERTY_TYPE' => 'N',
                    'IBLOCK_ID' => $iblockId
                ]
            );
        }

        return $property['ID'];
    }

    public function fillProperties()
    {
        $propertyBrandStmId = $this->checkPropertyCreated(self::PROPERTY_STM_CODE, 'СТМ', self::BRANDS_IBLOCK_ID);

        $sql = "
            SELECT
                product.ID product_id,
                CASE WHEN COALESCE(purchase_price.PRICE,0) > 0 AND COALESCE(sell_price.PRICE,0) > 0 AND COALESCE(sold_products.cnt,0) > 0
                    THEN (sell_price.PRICE - purchase_price.PRICE) * sold_products.cnt
                    ELSE 0
                END margin,
                brand_stm_property.VALUE is_stm
            FROM
                b_iblock_element product
                LEFT JOIN
                (
                    SELECT
                        basket.PRODUCT_ID,
                        COUNT(*) cnt
                    FROM
                        b_sale_basket basket
                        JOIN b_sale_order ordr
                            ON ordr.ID = basket.ORDER_ID
                    WHERE
                        ordr.DATE_INSERT >= DATE_SUB(curdate(), INTERVAL 1 MONTH)
                    GROUP BY
                        basket.PRODUCT_ID
                ) sold_products
                    ON sold_products.PRODUCT_ID = product.ID
                LEFT JOIN b_catalog_price purchase_price
                    ON purchase_price.PRODUCT_ID = product.ID
                    AND purchase_price.CATALOG_GROUP_ID = " . self::PURCHASE_PRICE_ID . "
                LEFT JOIN b_catalog_price sell_price
                    ON sell_price.PRODUCT_ID = product.ID
                    AND sell_price.CATALOG_GROUP_ID = " . self::SELL_PRICE_ID . "
                LEFT JOIN b_iblock_element_property brand_property
                    ON brand_property.IBLOCK_ELEMENT_ID = product.ID
                    AND brand_property.IBLOCK_PROPERTY_ID = " . self::PROPERTY_BRAND_ID . "
                LEFT JOIN b_iblock_element_property brand_stm_property
                    ON brand_stm_property.IBLOCK_ELEMENT_ID = brand_property.VALUE
                    AND brand_stm_property.IBLOCK_PROPERTY_ID = " . $propertyBrandStmId . "
            WHERE
                product.IBLOCK_ID = " . self::IBLOCK_ID . "
        ";

        $rowset = $this->db->query($sql);
        while ($row = $rowset->fetch()) {
            $this->db->queryExecute(
                'UPDATE b_iblock_element SET ' .
                self::IBLOCK_FIELD_PREFIX . self::PROPERTY_STM_CODE . ' = ' . (empty($row['is_stm']) ? 0 : 1) . ', ' .
                self::IBLOCK_FIELD_PREFIX . self::PROPERTY_MARGIN_CODE . ' = ' . ((int)$row['margin'])  . ' ' .
                ' WHERE ID = ' . $row['product_id']
            );
        }
    }

    public function updateStmBrands()
    {
        $brandList = [
            'mtx',
            'stels',
            'matrix',
            'bars',
            'shurup',
            'sparta',
            'stern',
            'kronwerk',
            'stroymash',
            'elfe',
            'noname',
            'sibrtekh',
            'gross',
            'palisad',
            'denzel',

            /*
            'alyumet',
            'amet',
            'arti',
            'baz',
            'gorizont',
            'ermak',
            'klever_s',
            'luga',
            'luga_abraziv',
            'niz',
            'praktika',
            'rossiya',
            'glazov',
            */
        ];

        $brands = \CIBlockElement::GetList(
            [],
            [
                'CODE' => $brandList,
                'IBLOCK_ID' => self::BRANDS_IBLOCK_ID
            ],
            false,
            false,
            ['ID', 'PROPERTY_' . self::PROPERTY_STM_CODE]
        );

        while ($brand = $brands->Fetch()) {
            if (empty($brand['PROPERTY_' . self::PROPERTY_STM_CODE])) {
                \CIBlockElement::SetPropertyValuesEx(
                    $brand['ID'],
                    self::BRANDS_IBLOCK_ID,
                    [self::PROPERTY_STM_CODE => 1],
                    []
                );
            }
        }
    }
}