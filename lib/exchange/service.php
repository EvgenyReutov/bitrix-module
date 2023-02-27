<?php

namespace Instrum\Main\Exchange;

use CIBlockProperty;
use Instrum\Main\Exchange\Dto;
use Instrum\Main\Exchange\Helpers\ArrayHelper;
use \CIBlockSection;
use \CCatalogProduct;
use \CIBlockElement;
use Instrum\Main\Exchange\Helpers\StringHelper;
use \RuntimeException;
use Bitrix\Main\DB\Connection;
use Bitrix\Iblock\PropertyIndex\Manager as FacetManager;

class Service implements ExchangeServiceInterface
{
    const IBLOCK_ID = 20;

    const FEATURE_TYPE_MAP = [
        Dto\Feature::TYPE_ENUM => 'L',
        Dto\Feature::TYPE_BOOLEAN => 'L', // Not a typo
        Dto\Feature::TYPE_STRING => 'S',
        Dto\Feature::TYPE_NUMERIC => 'N',
    ];

    const BOOL_TRUE_TEXT = 'Да';
    const BOOL_FALSE_TEXT = 'Нет';

    const TEMP_PRODUCT_TABLE_NAME = 'stuff_exchange_affected_';

    /** @var Connection */
    protected $db;

    /** @var Reader */
    protected $reader;

    /** @var array */
    protected $itemsMap;

    /**
     * Service constructor.
     * @param Connection $db
     * @param Reader $reader
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function __construct($db, $reader)
    {
        if(empty($db)) {
            throw new \RuntimeException('Database connection should be specified');
        }
        if(empty($reader)) {
            throw new \RuntimeException('Data reader should be specified');
        }

        $this->db = $db;
        $this->reader = $reader;

        $this->preloadCategoryUuidMap();
        $this->preloadFeatureUuidMap();
    }

    public function run()
    {
        $affectedCategories = $this->runCategories();
        $affectedFeatures = $this->runFeatures();
        $affectedProducts = $this->runProducts();

        if($this->reader->readRemoveUnaffected()) {
            if(!empty($affectedCategories)) {
                $this->removeUnaffectedCategories($affectedCategories);
            }
            if(!empty($affectedFeatures)) {
                $this->removeUnaffectedFeatures($affectedFeatures);
            }
            if(!empty($affectedProducts)) {
                $this->removeUnaffectedProducts($affectedProducts);
            }
        }

    }

    /**
     * @param string $className
     */
    protected function preloadItemsUuidMap($className)
    {
        if(!isset($this->itemsMap[$className])) {
            $this->itemsMap[$className] = [];
        }

        $rowset = $className::GetList([], [
            'IBLOCK_ID' => self::IBLOCK_ID,
        ]);
        while($row = $rowset->Fetch()) {
            if(!empty($row['XML_ID'])) {
                $this->itemsMap[$className][$row['XML_ID']] = $row['ID'];
            }
        }
    }

    /**
     * @param string $className
     * @param string $uuid
     * @return mixed|null
     */
    protected function getItemIdByUuid($className, $uuid)
    {
        if (!empty($uuid)) {
            if(!isset($this->itemsMap[$className])) {
                $this->itemsMap[$className] = [];
            }

            if (empty($this->itemsMap[$className][$uuid])) {
                $list = $className::GetList([], [
                    'XML_ID' => $uuid,
                    'IBLOCK_ID' => self::IBLOCK_ID,
                ]);
                $item = $list->Fetch();
                if($item) {
                    $this->itemsMap[$className][$uuid] = $item['ID'];
                }
            }

            return $this->itemsMap[$className][$uuid];
        }
        return null;
    }

    /**
     * @param $uuid
     * @return mixed|null
     */
    protected function getCategoryIdByUuid($uuid)
    {
        return $this->getItemIdByUuid(CIBlockSection::class, $uuid);
    }

    /**
     * @param $uuid
     * @return mixed|null
     */
    protected function getFeatureIdByUUid($uuid)
    {
        return $this->getItemIdByUuid(CIBlockProperty::class, $uuid);
    }

    /**
     * @param $uuid
     * @return mixed|null
     */
    protected function getProductIdByUUid($uuid)
    {
        return $this->getItemIdByUuid(CIBlockElement::class, $uuid);
    }

    /**
     *
     */
    protected function preloadCategoryUuidMap()
    {
        $this->preloadItemsUuidMap(CIBlockSection::class);
    }

    /**
     *
     */
    protected function preloadFeatureUuidMap()
    {
        $this->preloadItemsUuidMap(CIBlockProperty::class);
    }

    /**
     * @param string $table
     * @param string $name
     * @param string $separator
     * @param bool $upper
     * @return string
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function generateNewUniqueCode($table, $name, $separator = '-', $upper = false, $canBeginWithNumber = true)
    {
        $url = StringHelper::prepareUrl($name, $separator);

        if(!$canBeginWithNumber) {
            if(preg_match('/^\d/', $url) === 1) {
                $url = 'i' . $separator . $url;
            }
        }
        if($upper) {
            $url = mb_strtoupper($url);
        }

        while ($this->db->queryScalar("SELECT ID FROM " . $this->db->getSqlHelper()->forSql($table) . " WHERE CODE = '" . $this->db->getSqlHelper()->forSql($url) . "' AND IBLOCK_ID=" . self::IBLOCK_ID)) {
            $url = StringHelper::increment($url, $separator);
        }

        return $url;
    }

    /**
     * @param string $name
     * @return string
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function generateNewUniqueCategoryUrl($name)
    {
        return $this->generateNewUniqueCode('b_iblock_section', $name);
    }

    /**
     * @param string $name
     * @return string
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function generateNewUniqueFeatureCode($name)
    {
        return $this->generateNewUniqueCode('b_iblock_property', $name, '_', true, false);
    }

    /**
     * @param $name
     * @return string
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function generateNewUniqueProductCode($name)
    {
        return $this->generateNewUniqueCode('b_iblock_element', $name);
    }


    /**
     * @param string $className
     * @param int $id
     * @param array $data
     * @return int
     */
    protected function saveClassData($className, $id, $data)
    {
        $saved = false;
        $object = new $className;

        if(empty($id)) {
            $id = $object->Add($data);
            $saved = $id > 0;
        } else {
            $saved = $object->Update($id, $data);
        }

        if(!$saved) {
            throw new RuntimeException($object->LAST_ERROR);
        }

        return $id;
    }

    /**
     * @param string $className
     * @param string $tableName
     * @param array $affectedIds
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function removeUnaffected($className, $tableName, $affectedIds)
    {
        $rowset = $this->db->query("
            SELECT
                el.ID,
                el.XML_ID,
                el.NAME
            FROM
                " . $tableName . " el
            WHERE
                el.IBLOCK_ID = " . self::IBLOCK_ID . "
                AND el.ACTIVE = 'Y' 
                AND NOT el.ID IN (" . join(',', $affectedIds) . ")
        ");

        while ($row = $rowset->fetch()) {
            if(StringHelper::isUuid($row['XML_ID']) && !StringHelper::startsWith($row['NAME'], '_')) {
                $this->saveClassData($className, $row['ID'], ['ACTIVE' => 'N']);
            }
        }
    }

    /**
     * @return array
     */
    public function runCategories()
    {
        $categories = [];
        foreach ($this->reader->readCategories() as $category) {
            $categories[] = $category;
        }

        $categories = ArrayHelper::sortHierarchy($categories, 'uuid', 'parent_uuid');
        $affectedIds = [];

        $rootUuid = null;

        /** @var Dto\Category $category */
        foreach ($categories as $category) {
            if(empty($category->parent_uuid)) {
                $rootUuid = $category->uuid;
            } else {
                if($category->parent_uuid == $rootUuid) {
                    $category->parent_uuid = null;
                }
                $categoryId = $this->getCategoryIdByUuid($category->uuid);
                $parentId = $this->getCategoryIdByUuid($category->parent_uuid);

                $data = [
                    'ACTIVE' => 'Y',
                    'IBLOCK_SECTION_ID' => $parentId,
                    'IBLOCK_ID' => self::IBLOCK_ID,
                    'NAME' => $category->name,
                    'XML_ID' => $category->uuid
                ];

                if(empty($categoryId)) {
                    $data['CODE'] = $this->generateNewUniqueCategoryUrl($category->name);
                }
                $categoryId = $this->saveClassData(CIBlockSection::class, $categoryId, $data);
                $affectedIds[] = $categoryId;

            }
        }

        return $affectedIds;
    }


    /**
     * @return array
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function runFeatures()
    {
        $affectedIds = [];

        /** @var Dto\Feature $feature */
        foreach ($this->reader->readFeatures() as $feature) {

            $featureId = $this->getFeatureIdByUUid($feature->uuid);

            $data = [
                'NAME' => $feature->name,
                'ACTIVE' => 'Y',
                'PROPERTY_TYPE' => self::FEATURE_TYPE_MAP[$feature->type],
            ];

            if($feature->type == Dto\Feature::TYPE_ENUM || $feature->type == Dto\Feature::TYPE_BOOLEAN) {
                $data['VALUES'] = [];

                $existingEnums = CIBlockProperty::GetPropertyEnum($featureId, ['SORT'=>'ASC']);
                while($item = $existingEnums->Fetch()) {
                    $data['VALUES'][$item['ID']] = [
                        'XML_ID' => $item['XML_ID'],
                        'VALUE' => null,
                        'SORT' => $item['SORT'],
                    ];
                }

                if($feature->type == Dto\Feature::TYPE_ENUM) {
                    /** @var Dto\FeatureValue $featureValue */
                    foreach ($feature->enum as $featureValue) {
                        $found = false;
                        foreach ($data['VALUES'] as $valueId => $valueData) {
                            if($valueData['XML_ID'] == $featureValue->uuid) {
                                $data['VALUES'][$valueId]['VALUE'] = $featureValue->name;
                                $found = true;
                            }
                        }
                        if(!$found) {
                            $data['VALUES'][] = [
                                'VALUE' => $featureValue->name,
                                'XML_ID' => $featureValue->uuid
                            ];
                        }
                    }
                } else {
                    $data['VALUES'] = [
                        [
                            'VALUE' => self::BOOL_TRUE_TEXT,
                            'XML_ID' => 'true'
                        ],
                        [
                            'VALUE' => self::BOOL_FALSE_TEXT,
                            'XML_ID' => 'false'
                        ],
                    ];
                }
            }

            if(empty($featureId)) {
                $data['CODE'] = $this->generateNewUniqueFeatureCode($feature->name);
                $data['IBLOCK_ID'] = self::IBLOCK_ID;
                $data['XML_ID'] = $feature->uuid;
            }
            $featureId = $this->saveClassData(CIBlockProperty::class, $featureId, $data);
            $affectedIds[] = $featureId;

        }

        return $affectedIds;
    }

    /**
     * @return array
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function runProducts()
    {
        $affectedIds = [];
        $i = 0;

        /** @var Dto\Product $product */
        foreach ($this->reader->readProducts() as $product) {
            $productId = $this->getProductIdByUUid($product->uuid);

            $iblockData = [
                'IBLOCK_ID' => self::IBLOCK_ID,
                'NAME' => $product->name,
                'ACTIVE' => 'Y',
                'DETAIL_TEXT_TYPE' => 'html',
                'DETAIL_TEXT' => nl2br($product->description, false)
            ];
            if (!empty($productId) && in_array(
                    $productId,
                    [
                        217046,
                        217049,
                        217054,
                        180662,
                        180672,
                        217007,
                        217012,
                        226248,
                        226265,
                        226289,
                        177857,
                        234361,
                        234363,
                        234445,
                        234446,
                        234447,
                        234448,
                        234449,
                        234450,
                        234451,
                        234452,
                        234453,
                        234454,
                        234455,
                        234456,
                        234457,
                        234458,
                        234459,
                        234460,
                        234461,
                        234462,
                        234463,
                        234464,
                        234465,
                        234466,
                        234467,
                        190324,
                        197191,

                        149478,181210,149479,181211,149480,181212,119743,185371,119744,185372,119745,185373,119746,185374,119623,188896,119747,185375,119748,185376,119749,185377,151273,178400,129523,178399,129525,178401,129528,178404,129530,178406,129526,178402,129527,178403,129529,178405,149315,180484,149313,180481,149311,180479,149314,180482,149312,180480,169365,198060,126816,179909,126817,179910,198301,198302,131992,184884,131993,184885,131994,184886,126750,179844,126755,179849,120624,187994,120625,187995,134968,182799,134969,182800,134970,182801,134971,182802,134972,182803,134973,182804,134974,182805,134975,182806,134976,182807,131995,184887,127393,180483,127388,180478,131996,216879,131997,184888,127405,180495,127404,180494,120626,187996,127400,180490,134980,182811,134981,182812,134982,182813,198303,198304,198305,198306,198307,198308,198309,198310,198311,198312,198313,198314,198315,198316,198317,198318,198319,198320,131998,184889,134985,182816,131999,184890,121160,190129,120546,187561,133573,181766,132001,184892,124838,189628,126754,179848,126751,179845,134986,182817,120627,187997,134988,182819,132699,184893,132700,184894,134989,182820,134990,182821,132701,184895,134991,182822,126748,179842,126753,179847,134992,182823,132702,216880,134993,182824,132703,184896,134994,182825,132704,184897,132705,184898,132706,184899,126490,179328,126493,179331,129038,170078,128267,170076,128268,170077,129041,170081,129042,170082,132707,184900,129040,170080,132708,184901,129045,170085,129039,170079,129043,170083,129046,170086,129044,170084,128007,169785,128006,169784,128005,169783,128004,169782,128003,169781,129047,170087,129048,170088,129049,170089,126492,179330,126491,179329,152209,181209,152627,179850,126749,179843,119502,188592,119503,188593,119504,188594,119505,188595,119506,188596,129797,178651,152210,216774,149477,181206,152207,181207,198321,198322,198323,198324,198325,152208,181208,198326,198327,198328,198329,198330,198331,198332,198333,198334,198335,198336,198337,198338,198339,198340,198341,198342,
                        131093,182919,128475,177852,131115,182941,131118,182944,120736,186784,129220,177446,130897,183900,
                    ],
                    false
                )) {
                $oldData = CIBlockElement::GetByID($productId)->Fetch();
                if (!empty($oldData)) {
                    $iblockData['DETAIL_TEXT_TYPE'] = $oldData['DETAIL_TEXT_TYPE'];
                    $iblockData['DETAIL_TEXT'] = $oldData['DETAIL_TEXT'];
                }
            }

            $productCategoryIds = [];
            if(!empty($product->categories)) {
                foreach ($product->categories as $categoryUuid) {
                    $categoryId = $this->getCategoryIdByUuid($categoryUuid);
                    if(!empty($categoryId)) {
                        $productCategoryIds[] = $categoryId;
                    }
                }
            }

            if(!empty($productCategoryIds)) {
                $iblockData['IBLOCK_SECTION_ID'] = $productCategoryIds[0];
            }

            if(empty($productId)) {
                $iblockData['CODE'] = $this->generateNewUniqueProductCode($product->name);
                $iblockData['XML_ID'] = $product->uuid;
            }

//		$iblockData['CODE'] = $this->generateNewUniqueProductCode($product->name);

            $UpdateClassData = false;
            $oldData = CIBlockElement::GetByID($productId)->Fetch();
            foreach ($iblockData as $prop => $value) {
                if ((string)$oldData[$prop] != (string)$value) {
                    $UpdateClassData = true;
                }
            }
            if ($UpdateClassData) {
                $productId = $this->saveClassData(CIBlockElement::class, $productId, $iblockData);
            }

            $productData = [
                'ID' => $productId,
                'WEIGHT' => $product->weight,
                'LENGTH' => $product->length,
                'WIDTH' => $product->width,
                'HEIGHT' => $product->height,
                'VAT_ID' => 3, /*20%*/
                'VAT_INCLUDED' => 'Y'
            ];

            $ar_res = CCatalogProduct::GetByID($productId);
            $UpdateCatalogProduct = false;
            foreach ($productData as $prop => $value) {
                if ((string)$ar_res[$prop] != (string)$value) {
                    $UpdateCatalogProduct = true;
                }
            }
            if($UpdateCatalogProduct) {
                if (!CCatalogProduct::Add($productData)) {
                    throw new RuntimeException('Could not save product data');
                }
            }

            // тут может быть проблема...
            $rowset = $this->db->query("SELECT BS.ID AS ID FROM b_iblock_section_element SE INNER JOIN b_iblock_section BS ON SE.IBLOCK_SECTION_ID = BS.ID INNER JOIN b_iblock B ON B.ID = BS.IBLOCK_ID WHERE SE.IBLOCK_ELEMENT_ID in ($productId)");
            // тут может быть проблема...
            $arOldSections = [];
            while($arSection = $rowset->fetch()) {
                $arOldSections[] = $arSection["ID"];
            }
            sort($arOldSections);
            sort($productCategoryIds);
            if ($arOldSections != $productCategoryIds) {
                CIBlockElement::SetElementSection($productId, $productCategoryIds);
            }

            $featuresData = [];

            $rowset = CIBlockElement::GetProperty(
                self::IBLOCK_ID,
                $productId,
                [],
                []
            );
            while ($row = $rowset->fetch()) {
                if(isset($row['VALUE'])) {
                    if(StringHelper::startsWith($row['NAME'], '_') || !StringHelper::isUuid($row['XML_ID'])) {
                        if(!in_array($row['CODE'], ['MORE_PHOTO', 'ATTACHMENTS', 'FILES'])){
                            $featuresData[$row['CODE']] = $row['VALUE'];
                        }
                    }
                }
            }

            if(!empty($product->sku)) {
                $featuresData['CML2_ARTICLE'] = $product->sku;
            }
            if(!empty($product->barcode)) {
                $featuresData['CML2_BAR_CODE'] = $product->barcode;
            }


            if(!empty($product->featureValues)) {
                $featureUuids = ArrayHelper::pluck($product->featureValues, 'uuid');
                $featureUuids = array_map(function($value) { return "'$value'"; }, $featureUuids);
                $featureValues = ArrayHelper::map($product->featureValues, 'uuid', 'value');

                $enumValues = [];

                $enumUuids = array_filter($featureValues, function ($value) {
                    return StringHelper::isUuid($value);
                });

                if(!empty($enumUuids)) {

                    $enumUuids = array_map(function($value) { return "'$value'"; }, $enumUuids);

                    $rowset = $this->db->query("
                        SELECT
                            enm.XML_ID,
                            enm.ID,
                            enm.VALUE
                        FROM
                            b_iblock_property_enum enm
                            JOIN b_iblock_property prop
                                ON prop.ID = enm.PROPERTY_ID
                                AND prop.IBLOCK_ID = " . self::IBLOCK_ID . "
                        WHERE
                            enm.XML_ID IN (" . join(',', $enumUuids) . ")
                    ");

                    while ($row = $rowset->fetch()) {
                        $enumValues[$row['XML_ID']] = $row;
                    }
                }

                $rowset = $this->db->query("
                    SELECT
                        prop.ID,
                        prop.XML_ID,                        
                        prop.CODE,
                        prop.PROPERTY_TYPE
                    FROM
                        b_iblock_property prop
                    WHERE
                        prop.IBLOCK_ID = " . self::IBLOCK_ID . "
                        AND prop.XML_ID IN (" . join(',', $featureUuids) . ")
                ");

                while ($row = $rowset->fetch()) {
                    $fv = $featureValues[$row['XML_ID']];
                    if(!empty($enumValues[$fv])) {
                        $fv = $row['PROPERTY_TYPE'] == 'L' ? $enumValues[$fv]['ID'] : $enumValues[$fv]['VALUE'];
                    } elseif ($row['PROPERTY_TYPE'] == 'L') {
                        $boolVal = filter_var($fv, FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
                        if($boolVal !== null) {

                            $fvrowset = $this->db->query("
                                SELECT
                                    ID
                                FROM
                                    b_iblock_property_enum
                                WHERE
                                    PROPERTY_ID = " . $row['ID'] . "
                                    AND XML_ID = '" . ($boolVal ? 'true' : 'false') . "'                                    
                            ");
                            if($fwrow = $fvrowset->fetch()) {
                                $fv = $fwrow['ID'];
                            }
                        }
                    }
                    if(!is_numeric($fv)) {
                        $boolVal = filter_var($fv, FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
                        if($boolVal !== null) {
                            $fv = $boolVal ? self::BOOL_TRUE_TEXT : self::BOOL_FALSE_TEXT;
                        }
                    }
                    $featuresData[$row['CODE']] = $fv;
                }
            }

            if(!empty($featuresData)) {
                CIBlockElement::SetPropertyValues($productId, self::IBLOCK_ID, $featuresData);
            }

            try {
                FacetManager::updateElementIndex(self::IBLOCK_ID, $productId);
            } catch (\Throwable $e) {

            }

            $affectedIds[] = $productId;

            ++$i;
            fwrite(STDOUT, "$i\r");
        }

        return $affectedIds;
    }

    /**
     * @param int[] $affectedIds
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function removeUnaffectedCategories($affectedIds)
    {
        $affectedIds[] = 5547;
        $this->removeUnaffected(CIBlockSection::class, 'b_iblock_section', $affectedIds);
    }

    /**
     * @param int[] $affectedIds
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function removeUnaffectedFeatures($affectedIds)
    {
        $this->removeUnaffected(CIBlockProperty::class, 'b_iblock_property', $affectedIds);
    }

    /**
     * @param int[] $affectedIds
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function removeUnaffectedProducts($affectedIds)
    {
        $tempTableName = self::TEMP_PRODUCT_TABLE_NAME . time() . '_' . rand(0,99);

        $this->db->queryExecute("
            CREATE TABLE IF NOT EXISTS $tempTableName (
                id INT NOT NULL AUTO_INCREMENT,
                product_id INT NOT NULL,
                PRIMARY KEY (id)
            );
        ");

        foreach($affectedIds as $affectedId) {
            $this->db->queryExecute("INSERT INTO $tempTableName(product_id) VALUES ($affectedId);");
        }

        $this->db->queryExecute("
            UPDATE
                b_iblock_element ll
                LEFT JOIN
                    $tempTableName vlbl
                    ON ll.ID = vlbl.product_id
            SET
                ACTIVE = 'N'
            WHERE
                ll.IBLOCK_ID = " . self::IBLOCK_ID . "
                AND vlbl.id IS NULL
        ");

        $this->db->queryExecute("DROP TABLE $tempTableName");

    }
}
