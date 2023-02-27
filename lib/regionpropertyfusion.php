<?php


namespace Instrum\Main;


use Bitrix\Catalog\Model\Price;
use Bitrix\Catalog\Model\Product;
use Bitrix\Main\Application;
use CIBlockElement;
use CIBlockProperty;
use RuntimeException;
use Sotbit\Regions\Internals\RegionsTable;

class RegionPropertyFusion
{
    const PREFIX_STORE_COUNT = 'CATALOG_QUANTITY_';
    const NAME_STORE_COUNT = '_КОЛИЧЕСТВО НА СКЛАДЕ';

    const PREFIX_REGION_AVAILABILITY = 'REGION_AVAILABLE_';
    const NAME_REGION_AVAILABILITY = '_ДОСТУПНОСТЬ_В_РЕГИОНЕ_';

    const PROPERTY_SORT_VALUE = 97;

    const PUBLIC_PRICE_GROUP_ID = 3;

    /** @var int */
    protected $iblockId;

    protected $db;

    /**
     * RegionProperty constructor.
     * @param int $iblockId
     */
    public function __construct($iblockId)
    {
        if (empty($iblockId)) {
            throw new RuntimeException('Iblock ID should not be empty');
        }
        $this->iblockId = $iblockId;

        $this->db = Application::getConnection();
    }

    /**
     * @param array $storeIds
     */
    public function checkStorePropertiesCreated($storeIds)
    {
        if (!is_array($storeIds)) {
            throw new RuntimeException('Store IDs should be provided as array');
        }

        foreach ($storeIds as $storeId) {
            $this->checkPropertyCreated(self::PREFIX_STORE_COUNT . $storeId, self::NAME_STORE_COUNT . $storeId);
        }
    }

    /**
     * @param array $regionIds
     */
    public function checkRegionPropertiesCreated($regionIds)
    {
        if (!is_array($regionIds)) {
            throw new RuntimeException('Region IDs should be provided as array');
        }

        foreach ($regionIds as $regionId) {
            $this->checkPropertyCreated(
                self::PREFIX_REGION_AVAILABILITY . $regionId,
                self::NAME_REGION_AVAILABILITY . $regionId
            );
        }
    }

    /**
     * @param array $regionStoreMap
     */
    public function checkProperties($regionStoreMap)
    {
        if (!empty($regionStoreMap)) {
            $this->checkRegionPropertiesCreated(array_keys($regionStoreMap));
            foreach ($regionStoreMap as $regionId => $regionStores) {
                $this->checkStorePropertiesCreated($regionStores);
            }
        }
    }

    /**
     * @param array $regionStoreMap
     * @param array $storeAmounts
     */
    public function processAmounts($productId, $regionStoreMap, $storeAmounts)
    {
        $calcStoreAmount = [];
        $regionCount = array_fill_keys(array_keys($regionStoreMap), 0);
        foreach ($regionStoreMap as $regionId => $regionStores) {
            foreach ($regionStores as $storeId) {
                $calcStoreAmount[$storeId] = 0;
                if (!empty($storeAmounts[$storeId])) {
                    $calcStoreAmount[$storeId] = $storeAmounts[$storeId];
                }
            }
            $regionCount[$regionId] = array_sum(array_intersect_key($storeAmounts, array_flip($regionStores)));
        }

        foreach ($regionCount as $regionId => $regionSum) {
            $this->setProductPropertyValue(
                $productId,
                self::PREFIX_REGION_AVAILABILITY . $regionId,
                $regionSum > 0 ? 1 : 0
            );
        }

        foreach ($calcStoreAmount as $storeId => $storeAmount) {
            $this->setProductPropertyValue($productId, self::PREFIX_STORE_COUNT . $storeId, $storeAmount);
        }
    }

    /**
     * @param int $productId
     * @param string $code
     * @param string $name
     */
    protected function checkPropertyCreated($code, $name)
    {
        if (empty($code)) {
            throw new RuntimeException('Property code should not be empty');
        }
        if (empty($name)) {
            throw new RuntimeException('Property name should not be empty');
        }

        $property = CIBlockProperty::GetList(
            [],
            [
                'CODE' => $code,
                'IBLOCK_ID' => $this->iblockId
            ]
        )->Fetch();
        if (empty($property)) {
            $property = new CIBlockProperty;
            $property->Add(
                [
                    'NAME' => $name,
                    'ACTIVE' => 'Y',
                    'SORT' => self::PROPERTY_SORT_VALUE,
                    'CODE' => $code,
                    'PROPERTY_TYPE' => 'N',
                    'IBLOCK_ID' => $this->iblockId
                ]
            );
        }
    }

    /**
     * @param int $productId
     * @param string $code
     * @param mixed $value
     */
    protected function setProductPropertyValue($productId, $code, $value)
    {
        if (empty($productId)) {
            throw new RuntimeException('Product ID should not be empty');
        }
        if (empty($code)) {
            throw new RuntimeException('Property code should not be empty');
        }

        CIBlockElement::SetPropertyValues(
            $productId,
            $this->iblockId,
            $value,
            $code
        );
    }

    public function getRegionStoresMap()
    {
        static $regionStoresMap = null;

        if ($regionStoresMap === null) {
            $regionStoresMap = [];
            $regions = RegionsTable::GetList(
                [
                    'select' => ['ID', 'STORE'],
                ]
            )->fetchAll();

            foreach ($regions as $region) {
                if (!empty($region['STORE'])) {
                    $regionStoresMap[$region['ID']] = $region['STORE'];
                }
            }
        }

        return $regionStoresMap;
    }

    public function getProductStoresAmount($productId)
    {
        $result = [];
        $rowset = $this->db->query("
            SELECT
                p.ID,
                sp.STORE_ID,
                sp.AMOUNT
            FROM
                b_iblock_element p
                LEFT JOIN b_catalog_store_product sp
                    ON p.ID = sp.PRODUCT_ID
            WHERE                
                p.ID = $productId
            ORDER BY
                p.ID
        ");
        while ($row = $rowset->fetch()) {
            $result[$row['STORE_ID']] = (int)$row['AMOUNT'];
        }
        return $result;
    }

    public function processAvailability($productId)
    {
        $priceValue = 0;

        $price = Price::getList(
            [
                'filter' => [
                    'PRODUCT_ID' => $productId,
                    'CATALOG_GROUP_ID' => self::PUBLIC_PRICE_GROUP_ID
                ],
                'select' => ['ID', 'PRICE']
            ]
        )->fetch();

        if($price) {
            $priceValue = (float)$price['PRICE'];
        }

        $storesAmount = $this->getProductStoresAmount($productId);

        if($priceValue > 0) {
            $this->processAmounts($productId, $this->getRegionStoresMap(), $storesAmount);
        } else {
            foreach ($this->getRegionStoresMap() as $regionId => $stores) {
                $this->setProductPropertyValue(
                    $productId,
                    self::PREFIX_REGION_AVAILABILITY . $regionId,
                    0
                );
            }
        }

        $totalAmount = array_sum($storesAmount);
        $productQuery = Product::update(
            $productId,
            [
                'QUANTITY' => $totalAmount,
                'AVAILABLE' => $totalAmount > 0 ? 'Y' : 'N'
            ]
        );
        if(!$productQuery->isSuccess()) {
            throw new RuntimeException('Cannot update total amount for product id ' . $productId);
        }
    }
}