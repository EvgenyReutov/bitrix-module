<?php

namespace Instrum\Main\Import;

use Bitrix\Main\Loader;
use CIBlockResult;
use Instrum\Main\Catalog\Product;
use Instrum\Main\Import\Table\ProductDataTable;

class PropertyExport{

    protected $iblock_id;
    protected $cache = [];
    protected $path;

    public function __construct($path)
    {
        print_r([$_SERVER["DOCUMENT_ROOT"]."/".$path]);
        $this->path = fopen($_SERVER["DOCUMENT_ROOT"]."/".$path, "w");
        $this->iblock_id = (new Product())->getIblockId();
        Loader::includeModule("iblock");
    }

    protected function getEndedSections()
    {

        if($this->cache["sections"])return $this->cache["sections"];

        $iSections = \CIBlockSection::GetList([], [
            "IBLOCK_ID" => $this->iblock_id,
            "ELEMENT_SUBSECTIONS" => "N",
            "CNT_ACTIVE" => "Y",
        ], true);

        $result = [];

        while($section = $iSections->GetNext())
        {
            if(intval($section["ELEMENT_CNT"]))
            {
                $chainResult = [];
                $chain = \CIBlockSection::GetNavChain($this->iblock_id, $section["ID"]);
                while($sectionChain = $chain->GetNext())
                {
                    $chainResult[] = $sectionChain["NAME"];
                }

                $section["CHAIN"] = implode("/", $chainResult);
                $result[] = $section;
            }
        }

        $this->cache["sections"] = $result;

        return $result;
    }

    protected function getIndexedSections($propertyId)
    {
        $result = [];
        foreach ($this->getEndedSections() as $sectionId => $sectionData)
        {
            if([
                \CIBlockElement::GetList([], [
                    "IBLOCK_ID" => $this->iblock_id,
                    "SECTION_ID" => $sectionId,
                    "!PROPERTY_".$propertyId."_VALUE" => False
                ])->SelectedRowsCount()
            ]){
                $result[] = $sectionData["CHAIN"];
            }
        }

        return implode("\n", $result);
    }

    public function run()
    {

        fputcsv($this->path, [
            "Имя",
            "GUID",
            "ID сайта",
            "Признак участия в фильтрации",
            //"Группа",
            "Разделы где есть фильтрация по свойству",
            "Тип свойства",
        ]);

        $iblock = \CIBlock::GetByID($this->iblock_id)->GetNext();

        $iProps = \CIBlockProperty::GetList([], ["IBLOCK_ID" => $this->iblock_id]);

        while($property = $iProps->GetNext())
        {
            $arResult = [
                "NAME" => $property['NAME'],
                "XML_ID" => $property["XML_ID"],
                "LID" => $iblock["LID"],
                "FILTRABLE" => $property["FILTRABLE"],
                //"GROUP" => false,
                "SECTIONS" => $this->getIndexedSections($property["ID"]),
                "TYPE" => null
            ];

            $arType = [$property["PROPERTY_TYPE"]];
            if($property["USER_TYPE"])
            {
                $arType[] = $property["USER_TYPE"];
            }
            $arResult["TYPE"] = implode("::", $arType);

            fputcsv($this->path, array_values($arResult));
        }

    }



}