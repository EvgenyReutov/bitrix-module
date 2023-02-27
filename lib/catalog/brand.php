<?php

namespace Instrum\Main\Catalog;

use Bitrix\Main\Application;
use Instrum\Main\Iblock\Element;
use \Bitrix\Main\Data\Cache;
use \CIBlockElement;
use \CIBlockPropertyEnum;
use \Cutil;

class Brand
{
    const PROPERTY_CODE_BRAND = 'BRAND_';
    const PROPERTY_CODE_AVAILABLE = 'REGION_AVAILABLE_';

    public $IBLOCK_CODE = "brands";
    public $product;
    public $collection;
    public $iblockID;
    public $app;
    public $catalogIblockID;
    protected $regionId;
    private $id;

    public function __construct()
    {
        $this->product = new Product();
        $this->collection = new Element\Collection();
        $this->iblockID = $this->getIblockId();
        $this->catalogIblockID = $this->product->getIblockId();

        $this->app = Application::getConnection();
    }

    public function setItemId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
        return $this;
    }

    public function getSections(){
        if(empty($this->id)){
            throw new \Exception("не задан идентификатор бренда");
        }
        $arSections = false;
        $cache = Cache::createInstance();
        if ($cache->initCache(60 * 60 * 24 * 7 * 0, md5($this->id . $this->catalogIblockID . $this->regionId), "/brand_sections")) {
            $vars = $cache->getVars();
            $arSections = $vars["arSections"];
        } elseif ($cache->startDataCache()) {
            $regionAvailableSubQuery = '';
            if(!empty($this->regionId)) {
                $regionAvailableSubQuery = "
                    JOIN b_iblock_property available_feature
                        ON available_feature.CODE = '" . self::PROPERTY_CODE_AVAILABLE . $this->regionId . "'
                        AND available_feature.IBLOCK_ID = 20
                    JOIN b_iblock_element_property available_feature_value
                        ON available_feature_value.IBLOCK_PROPERTY_ID = available_feature.ID
                        AND available_feature_value.VALUE = '1'
                        AND available_feature_value.IBLOCK_ELEMENT_ID = product_element.ID
                ";
            }

            $sectionsQuery = "
                SELECT DISTINCT
                    category.ID SECTION_ID,
                    category.IBLOCK_ID IBLOCK_ID
                FROM
                    b_iblock_element_property brand_feature_value
                    JOIN b_iblock_property brand_feature
                        ON brand_feature.CODE = '" . self::PROPERTY_CODE_BRAND . "'
                        AND brand_feature.IBLOCK_ID = {$this->catalogIblockID}
                        AND brand_feature.ID = brand_feature_value.IBLOCK_PROPERTY_ID
                    JOIN b_iblock_element product_element
                        ON product_element.IBLOCK_ID = {$this->catalogIblockID}
                        AND product_element.ID = brand_feature_value.IBLOCK_ELEMENT_ID
                        AND product_element.ACTIVE = 'Y'
                    JOIN b_iblock_section_element as product_category
                        ON product_category.IBLOCK_ELEMENT_ID = product_element.ID
                    JOIN b_iblock_section category
                        ON category.IBLOCK_ID = {$this->catalogIblockID}
                        AND category.ACTIVE = 'Y'
                        AND category.GLOBAL_ACTIVE = 'Y'
                        AND category.ID = product_category.IBLOCK_SECTION_ID
                    $regionAvailableSubQuery
                WHERE
                    brand_feature_value.VALUE = {$this->id}
            ";

            $rs = $this->app->query($sectionsQuery);
            $arSections = [];

            while ($result = $rs->fetch()) {
                $rsTree = \CIBlockSection::GetNavChain($this->catalogIblockID, $result['SECTION_ID']);
                while ($res = $rsTree->GetNext()) {
                    if ($res['DEPTH_LEVEL'] > 2) {
                        continue;
                    }

                    if ($res["DEPTH_LEVEL"] > 1) {
                        $arIds = array_column($arSections[$res['IBLOCK_SECTION_ID']]['ITEMS'], 'ID');
                        if (!in_array($res['ID'], $arIds)) {
                            $arSections[$res["IBLOCK_SECTION_ID"]]["ITEMS"][] = $res;
                        }
                    } else {
                        if (!isset($arSections[$res["ID"]])) {
                            $arSections[$res["ID"]] = $res;
                        }
                    }
                }
            }
            $name  = array_column($arSections, 'SORT');
            array_multisort($name, SORT_ASC, $arSections);

            $cache->endDataCache(["arSections" => $arSections]);
        }
        return $arSections;
    }

    public function getAvailableSection()
    {
        $arSections = false;
        $cache = Cache::createInstance();
        if ($cache->initCache(60 * 60 * 24 * 7 *0, "brands_sections", "/brands_section")) {
            $vars = $cache->getVars();
            $arSections = $vars["arSections"];
        } elseif ($cache->startDataCache()) {
            $rs = $this->app->query("
                select
                    sect.`NAME` as `SECTION_NAME`,
                    sect.`ID` as `SECTION_ID`,
                    GROUP_CONCAT(DISTINCT el1.`ID`) as `BRANDS`
                from
                    `b_iblock_element_property` prop
                inner join
                    `b_iblock_element` el
                on 
                    el.`ID` = prop.`IBLOCK_ELEMENT_ID`
                inner join
                    `b_iblock_property` p
                on
                    p.`ID` = prop.`IBLOCK_PROPERTY_ID`
                inner join
                    `b_iblock_element` el1
                on
                    prop.`VALUE` = el1.`ID`
                inner join
                    `b_iblock_section_element` el_sect
                on
                    el_sect.`IBLOCK_ELEMENT_ID` = el.`ID`
                inner join
                    `b_iblock_section` sect
                on
                    sect.`ID` = el_sect.`IBLOCK_SECTION_ID`
                where 
                    p.`CODE` = 'BRAND_' and
                    p.`IBLOCK_ID` = ".$this->catalogIblockID." and
                    el.`ACTIVE` = 'Y' and
                    el1.`ACTIVE` = 'Y' and
                    sect.`ACTIVE` = 'Y' and
                    sect.`GLOBAL_ACTIVE` = 'Y'
                group by
                    sect.`ID`
            ");
            $arSections = [];
            while ($result = $rs->fetch()) {
                $arBrands = explode(",", $result["BRANDS"]);
                if ($arTree = \CIBlockSection::GetNavChain($this->catalogIblockID, $result["SECTION_ID"])->GetNext()) {
                    $data = [
                        "NAME" => $arTree["NAME"],
                        "SECTION_ID" => $arTree["ID"],
                        "IBLOCK_ID" => $arTree["IBLOCK_ID"],
                        "LINK" => $arTree["SECTION_PAGE_URL"],
                    ];
                    if (isset($arSections[$arTree["ID"]]["BRANDS"])) {
                        $data["BRANDS"] = array_unique(array_merge($arSections[$arTree["ID"]]["BRANDS"], $arBrands));
                    } else {
                        $data["BRANDS"] = $arBrands;
                    }
                    $arSections[$arTree["ID"]] = $data;
                }
            }
            $cache->endDataCache(["arSections" => $arSections]);
        }        
        return $arSections;
    }

    public function getFilter(){
        if(!$this->id){
            return false;
        }
        $arFilter = false;

        // получим значение привязанных элементов

        $db_props = \CIBlockElement::GetProperty(
            $this->getIblockId(),
            $this->id,
            ['sort' => 'asc'],
            ['CODE' => 'PRODUCTS']
        );


        $arFilter['ID'] = [];
        while($result = $db_props->Fetch()){
            if($result['VALUE']) {
                $arFilter['ID'][] = $result['VALUE'];
            }
        }

        if(empty($arFilter['ID'])){
            $rs = $this->app->query("
                select
                    COUNT(bas.`PRODUCT_ID`) as CNT,
                    bas.`PRODUCT_ID`,
                    el1.`ACTIVE`
                from
                    `b_sale_order` ord
                inner join
                    `b_sale_basket` bas
                on
                    ord.`ID` = bas.`ORDER_ID`
                inner join
                    `b_iblock_element_property` prop
                on
                    prop.`IBLOCK_ELEMENT_ID` = bas.`PRODUCT_ID`
                inner join
                    `b_iblock_property` props
                on
                    props.`ID` = prop.`IBLOCK_PROPERTY_ID`
                inner join
                    `b_iblock_element` el
                on
                    el.`ID` = prop.`VALUE`
                inner join
                    `b_iblock_element` el1
                on
                    bas.`PRODUCT_ID` = el1.`ID`            
                where
                    props.`CODE` = 'BRAND_' and
                    el1.`ACTIVE` = 'Y' and                
                    el.`ID` = ".$this->id."            
                group by
                    bas.`PRODUCT_ID`
                order by
                    `CNT` desc
            ");
            $arFilter['ID'] = [];
            while($result = $rs->fetch()){
                $arFilter['ID'][] = $result['PRODUCT_ID'];
            }
        }
        $arFilter['>CATALOG_QUANTITY'] = 0;

        return $arFilter;
    }


    /**
     * парсер списочного свойства бренды в отдельный инфоблок
     * @throws \Exception
     */
    public function parseEnumBrands()
    {
        $rs = CIBlockPropertyEnum::GetList(
            ['DEF' => 'DESC', 'SORT' => 'ASC'],
            ['IBLOCK_ID' => $this->product->getIblockId(), 'CODE' => 'BREND']
        );
        while ($result = $rs->Fetch()) {
            $this->createNewItem($result);
        }
    }

    /**
     * проставление нового свойства бренда (должно быть заведено свойство BRAND_)
     * @throws \Exception
     */
    public function setProp()
    {
        $rs = CIBlockElement::GetList(
            ['ID' => 'ASC'],
            [
                'IBLOCK_ID' => $this->product->getIblockId(),
                '!PROPERTY_BREND' => false
            ],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'PROPERTY_BREND']
        );

        if ($rs) {
            $i = 0;
            $numRows = $rs->SelectedRowsCount();
            while ($result = $rs->Fetch()) {
                fwrite(STDOUT, $i . '/' . $numRows . "\r");

                $propertyValue = null;

                $enum = CIBlockPropertyEnum::GetByID($result['PROPERTY_BREND_ENUM_ID'])['XML_ID'];
                if(!empty($enum)) {
                    $elColl = new Element\Collection();
                    $elColl->setFilter([
                        'IBLOCK_ID' => $this->iblockID,
                        'XML_ID' => $enum
                    ]);

                    $rsBrand = $elColl->get();
                    if($rsBrand){
                        $propertyValue = $rsBrand->getId();
                    }
                }

                CIBlockElement::SetPropertyValues(
                    $result['ID'],
                    $result['IBLOCK_ID'],
                    $propertyValue,
                    'BRAND_'
                );

                ++$i;
            }
        }
    }

    /**
     * добавление свойства бренда с привязкой к инфоблоку брендов
     */
    public function addBrandProp()
    {
        $arFields = Array(
            'NAME' => 'Бренд',
            'ACTIVE' => 'Y',
            'SORT' => '100',
            'CODE' => 'BRAND_',
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => $this->iblockID,
            'IBLOCK_ID' => $this->product->getIblockId()
        );

        $ibp = new \CIBlockProperty;
        $PropID = $ibp->Add($arFields);
    }

    /**
     * @param $arFields
     * @throws \Exception
     */
    public function createNewItem($arFields)
    {
        $arData = [
            'IBLOCK_ID' => $this->iblockID,
            'NAME' => $arFields['VALUE'],
            'XML_ID' => $arFields['XML_ID'],
            'CODE' => Cutil::translit($arFields['VALUE'], 'ru', ['replace_space' => '_', 'replace_other' => '_']),
            'ACTIVE' => 'N',
        ];
        if (!$rs = $this->collection->setFilter([
            'IBLOCK_ID' => $this->iblockID,
            'CODE' => $arData['CODE']
        ])->get()) {
            (new Element\Item($arData))->save();
        }
    }

    /**
     * @return mixed
     */
    public function getIblockId()
    {
        return \CIBlock::GetList(
            [],
            ['CODE' => $this->IBLOCK_CODE]
        )->Fetch()['ID'];
    }


    /**
     * возвращает Id брендов - которые есть в товарах в данном разделе
     * @param $sectionId
     * @return array
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function getBrandsBySectionId($sectionId)
    {
        $result = [];
        echo '<pre>';
        echo ('$sectionId' . $sectionId);
        echo '</pre>';


        $rowset = $this->app->query(
            "
            SELECT
                propvalue.VALUE,
                COUNT(elemsect.IBLOCK_ELEMENT_ID) cnt
            FROM
                b_iblock_element_property propvalue
                JOIN b_iblock_section_element elemsect
                    ON elemsect.IBLOCK_ELEMENT_ID = propvalue.IBLOCK_ELEMENT_ID
                JOIN b_iblock_section thissection
                    ON thissection.ID = " . ((int)$sectionId) . "
                JOIN b_iblock_section childsectionsection
                    ON childsectionsection.ID = elemsect.IBLOCK_SECTION_ID
                    AND childsectionsection.IBLOCK_ID = thissection.IBLOCK_ID
                    AND childsectionsection.LEFT_MARGIN >= thissection.LEFT_MARGIN
                    AND childsectionsection.RIGHT_MARGIN <= thissection.RIGHT_MARGIN
                    AND childsectionsection.DEPTH_LEVEL >= thissection.DEPTH_LEVEL
                    AND childsectionsection.ACTIVE = 'Y'
            WHERE
                propvalue.IBLOCK_PROPERTY_ID = 12260
            GROUP BY
                propvalue.VALUE
            ORDER BY
                cnt DESC
        "
        );

        while ($row = $rowset->Fetch()) {
            $result[] = $row['VALUE'];
        }

        return $result;
    }
}