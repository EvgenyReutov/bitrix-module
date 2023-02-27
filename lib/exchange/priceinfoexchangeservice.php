<?php


namespace Instrum\Main\Exchange;

use Bitrix\Catalog\Model\Price;
use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Instrum\Main\Exchange\PriceInfoReaderInterface;
use Instrum\Main\Exchange\Dto;
use Instrum\Main\RegionPropertyFusion;
use \CCatalogGroup;
use \CIBlockElement;
use \RuntimeException;

class PriceInfoExchangeService
{
    /** @var PriceInfoReaderInterface */
    protected $reader;

    const PRICE_TYPE_TRANSLATE_MAP = [
        '94172f9e-e022-11df-82ac-001517556b49' => '8a11d965-cacb-11e8-80c4-0cc47a303247',
    ];

    const MAIN_PRICE_UUID = '8a11d965-cacb-11e8-80c4-0cc47a303247';

    /**
     * PriceInfoExchangeService constructor.
     * @param PriceInfoReaderInterface $reader
     */
    public function __construct($reader)
    {
        $this->reader = $reader;
    }

    protected function getConnection()
    {
        return Application::getConnection();
    }

    /**
     * @param $uuid
     * @return array
     */
    protected function getPricetypeByUuid($uuid)
    {
        return CCatalogGroup::GetList([], ['XML_ID' => $uuid])->Fetch();
    }

    protected function getProductByUUid($uuid)
    {
        return CIBlockElement::GetList([], ['IBLOCK_ID' => CATALOG_IB, 'XML_ID' => $uuid], false, false, [
            'ID', 'IBLOCK_ID', 'PROPERTY_OLD_PRICE'
        ])->Fetch();
    }

    protected function getProductPrices($productId)
    {
        /** @var Connection $db */
        $db = $this->getConnection();
        $result = [];
        $productId = (int)$productId;

        $rowset = $db->query(
            "
            SELECT
                pricetype.XML_ID,
                price.ID,
                price.PRICE
            FROM
                b_catalog_price price
                JOIN b_catalog_group pricetype
                    ON pricetype.ID = price.CATALOG_GROUP_ID
            WHERE
                price.PRODUCT_ID = $productId
        "
        );
        while ($row = $rowset->fetch()) {
            $result[$row['XML_ID']] = [
                'id' => $row['ID'],
                'value' => (float)$row['PRICE'],
            ];
        }

        return $result;
    }

    public function importPriceTypes()
    {
        /** @var Dto\PriceType $dtoPriceType */
        foreach ($this->reader->readPriceTypes() as $dtoPriceType) {
            $pricetypeuuid = $dtoPriceType->uuid;
            if (!empty(self::PRICE_TYPE_TRANSLATE_MAP[$pricetypeuuid])) {
                $pricetypeuuid = self::PRICE_TYPE_TRANSLATE_MAP[$pricetypeuuid];
            }

            $pricetype = $this->getPricetypeByUuid($pricetypeuuid);
            if (!$pricetype) {
                if (!CCatalogGroup::Add(
                    [
                        'BASE' => 'N',
                        'NAME' => $dtoPriceType->name,
                        'XML_ID' => $pricetypeuuid,
                        'USER_GROUP' => [1],
                        'USER_GROUP_BUY' => [1]
                    ]
                )) {
                    throw new RuntimeException('Cannot add new price type ' . $dtoPriceType->uuid);
                }
            }
        }
    }

    protected function getPriceTypeMap()
    {
        $result = [];
        $rowset = CCatalogGroup::GetList([], [], false, [], ['XML_ID', 'ID']);
        while ($row = $rowset->Fetch()) {
            $result[$row['XML_ID']] = $row['ID'];
        }
        return $result;
    }

    protected function getFusion()
    {
        $fusion = new RegionPropertyFusion(CATALOG_IB);
        $regionStoresMap = $fusion->getRegionStoresMap();
        $fusion->checkProperties($regionStoresMap);
        return $fusion;
    }

    public function importPrices()
    {
        $priceTypeMap = $this->getPriceTypeMap();
        $productUuid = null;
        $product = null;

        $fusion = $this->getFusion();

        /** @var Dto\PriceContainer $dtoPriceContainer */
        foreach ($this->reader->readPrices() as $dtoPriceContainer) {
            $product = $this->getProductByUUid($dtoPriceContainer->getProductUuid());
            if ($product) {
                $productPrices = $this->getProductPrices($product['ID']);

                $recalcAvailability = false;

                /** @var Dto\Price $dtoPrice */
                foreach ($dtoPriceContainer->getItems() as $dtoPrice) {
                    $pricetypeuuid = $dtoPrice->type_uuid;
                    if (!empty(self::PRICE_TYPE_TRANSLATE_MAP[$pricetypeuuid])) {
                        $pricetypeuuid = self::PRICE_TYPE_TRANSLATE_MAP[$pricetypeuuid];
                    }

                    $dtoPrice->value = (float)$dtoPrice->value;
                    $dtoPrice->old_value = (float)(empty($dtoPrice->old_value) || $dtoPrice->old_value <= $dtoPrice->value ? 0 : $dtoPrice->old_value);

                    if (isset($productPrices[$pricetypeuuid])) {
                        if ($dtoPrice->value !== $productPrices[$pricetypeuuid]['value']) {
                            if ($pricetypeuuid === self::MAIN_PRICE_UUID) {
                                if (($dtoPrice->value > 0 && $productPrices[$pricetypeuuid]['value'] <= 0) || ($dtoPrice->value <= 0 && $productPrices[$pricetypeuuid]['value'] > 0)) {
                                    $recalcAvailability = true;
                                }
                            }

                            $priceUpdateQuery = Price::update(
                                $productPrices[$pricetypeuuid]['id'],
                                ['PRICE' => $dtoPrice->value]
                            );

                            if(!$priceUpdateQuery->isSuccess()) {
                                throw new RuntimeException(
                                    'Cannot update price for product ' . $dtoPrice->product_uuid . ' with type ' . $pricetypeuuid . ': ' . implode(
                                        '; ',
                                        $priceUpdateQuery->getErrorMessages()
                                    )
                                );
                            }
                        }
                    } else {
                        $priceInsertQuery = Price::add(
                            [
                                'PRODUCT_ID' => $product['ID'],
                                'CATALOG_GROUP_ID' => $priceTypeMap[$pricetypeuuid],
                                'PRICE' => $dtoPrice->value,
                                'CURRENCY' => 'RUB'
                            ]
                        );

                        $productPrices[$pricetypeuuid] = [
                            'value' => $dtoPrice->value
                        ];

                        if ($pricetypeuuid === self::MAIN_PRICE_UUID) {
                            $recalcAvailability = true;
                        }

                        if (!$priceInsertQuery->isSuccess()) {
                            throw new RuntimeException(
                                'Cannot add price for product ' . $dtoPrice->product_uuid . ' with type ' . $pricetypeuuid . ': ' . implode(
                                    '; ',
                                    $priceInsertQuery->getErrorMessages()
                                )
                            );
                        }
                    }

                    if ($pricetypeuuid === self::MAIN_PRICE_UUID) {
                        $oldval = (float)$product['PROPERTY_OLD_PRICE_VALUE'];
                        if($oldval !== $dtoPrice->old_value) {
                            $salePercent = $dtoPrice->old_value > 0 ? round(100 * (1 - $dtoPrice->value / $dtoPrice->old_value)) : '';
                            CIBlockElement::SetPropertyValues($product['ID'], $product['IBLOCK_ID'], $dtoPrice->old_value, 'OLD_PRICE');
                            CIBlockElement::SetPropertyValues($product['ID'], $product['IBLOCK_ID'], $salePercent, 'SALE_PERCENT');
                        }
                    }
                }


                if($recalcAvailability) {
                    $fusion->processAvailability($product['ID']);
                }
            }
        }
       
    }
}