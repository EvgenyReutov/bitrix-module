<?php

namespace Instrum\Main\Import;

use CIBlockResult;
use Instrum\Main\Import\Table\ProductDataTable;

class ProductData{

    protected $from;
    protected $to;

    protected $linked = "CML2_ARTICLE";
    protected $db;

    protected $images;

    protected $fields = [
        "PREVIEW_PICTURE",
        "DETAIL_PICTURE",
        "PROPERTY_MORE_PHOTO"
    ];


    public function __construct($from, $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function setLinked($linked)
    {
        $this->linked = $linked;
        return $this;
    }

    protected function getElement($id)
    {
        if($element = \CIBlockElement::GetList([], ['ID' => $id])->GetNextElement())
        {
            $arElement = $element->GetFields();
            $arElement['PROPERTIES'] = $element->GetProperties();

            return $arElement;
        }
    }

    private function iterateCIBlockResult(\CDBResult $result, $print = false)
    {
        if($print)$this->print("[ITERATE]: ", $result->SelectedRowsCount(), "\n");
        for($i = 0; $element = $result->GetNext(); $i++)
        {
            if($print )$this->print("[I]: ", $i);
            yield $element;
        }
    }

    protected function fillFromData()
    {
        foreach($this->iterateCIBlockResult(\CIBlockElement::GetList(
            [],
            $this->getFromFilter(),
            false,
            false,
            ["ID", "PROPERTY_".$this->linked]
        ), true) as $element)
        {
            ProductDataTable::insertRow($element['ID'], $element['PROPERTY_'.$this->linked.'_VALUE']);
        }
    }

    protected function fillToData()
    {
        foreach($this->iterateCIBlockResult(\CIBlockElement::GetList(
            [],
            $this->getToFilter(),
            false,
            false,
            ["ID", "PROPERTY_".$this->linked]
        ), true) as $element)
        {
            ProductDataTable::updateRow($element['ID'], $element['PROPERTY_'.$this->linked.'_VALUE']);
        }
    }

    protected function getFilter($iblock)
    {
        return [
            'IBLOCK_ID' => $iblock,
            '!PROPERTY_'.$this->linked => False
        ];
    }

    protected function getFromFilter()
    {
        return static::getFilter($this->from);
    }

    protected function getToFilter()
    {
        return static::getFilter($this->to);
    }

    protected function print(...$msg)
    {
        fwrite(STDOUT, implode(" ", $msg)."\r");
    }

    protected function iterator()
    {
        $i = 1;
        $count = ProductDataTable::getCount();
        while (1)
        {
            $rs = ProductDataTable::getList([
                'limit' => 200
            ]);

            if(!$rs->getSelectedRowsCount()) break;

            while($row = $rs->fetch())
            {
                $this->print("[${i}/${count}]",$row["FROM_ID"],">", $row["TO_ID"]);
                yield $row;
                ProductDataTable::delete($row['ID']);
                $i += 1;

            }
        }
    }

    protected function merge($row)
    {
        //$this->print("[MERGE]", $row['FROM_ID'], ">", $row["TO_ID"]."\n");
        //$this->merge_price($row);
        //$this->merge_quantity($row);

        //$this->merge_images($row);
        $this->merge_codes($row);
    }

    public function merge_codes($row)
    {
        if(
            ($fromElement = $this->getElement($row['FROM_ID'])) &&
            ($toElement = $this->getElement($row['TO_ID']))
        )
        {
            (new \CIBlockElement())->Update($toElement['ID'], ['CODE' => $fromElement['CODE']]);
        }
    }

    public function merge_images($row)
    {
        if(
            ($fromElement = $this->getElement($row['FROM_ID'])) &&
            ($toElement = $this->getElement($row['TO_ID']))
        )
        {
            $arFields = [];
            $arProps = [];

            foreach($this->fields as $field)
            {
                if(strpos($field, "PROPERTY_") !== False)
                {
                    $propCode = str_replace("PROPERTY_", "", $field);
                    if(is_array($fromElement['PROPERTIES'][$propCode]['VALUE']))
                    {
                        $values = [];
                        foreach($fromElement['PROPERTIES'][$propCode]['VALUE'] as $value)
                        {
                            $values[] = \CFile::MakeFileArray(\CFile::GetFileArray($value)['SRC']);
                        }
                        $arProps[$propCode] = $values;
                    }
                    else if($value = $fromElement['PROPERTIES'][$propCode]['VALUE'])
                    {
                        $arProps[$propCode] = \CFile::MakeFileArray(\CFile::GetFileArray($value)['SRC']);
                    }
                }
                else if($value = $fromElement[$field])
                {
                    $arFields[$field] = \CFile::MakeFileArray(\CFile::GetFileArray($value)['SRC']);
                }
            }


            if($arFields) (new \CIBlockElement())->Update($toElement['ID'], $arFields);
            if($arProps) \CIBlockElement::SetPropertyValuesEx($toElement['ID'], $toElement['IBLOCK_ID'], $arProps);

        }
    }

    protected function merge_quantity($row)
    {
        $from = \Bitrix\Catalog\Model\Product::getList(['filter' => ['ID' => $row['FROM_ID']]])->fetch();
        \Bitrix\Catalog\Model\Product::update($row['TO_ID'], ["QUANTITY" => $from["QUANTITY"]]);
    }

    protected function merge_price($row)
    {
        foreach($this->iterateCIBlockResult(\CPrice::GetList([], ["PRODUCT_ID" => $row["FROM_ID"]])) as $price)
        {
            $this->updatePrice($row['TO_ID'], $price['CATALOG_GROUP_ID'], $price['PRICE']);
        }
    }

    protected function updatePrice($toId, $priceType, $priceValue)
    {
        if($price = \CPrice::GetList([], ["PRODUCT_ID" => $toId, "CATALOG_GROUP_ID" => $priceType])->Fetch())
        {
            \Bitrix\Catalog\Model\Price::update($price['ID'], ['VALUE' => $priceType]);
        }
        else
        {
            \Bitrix\Catalog\Model\Price::add([
                "PRODUCT_ID" => $toId,
                "CATALOG_GROUP_ID" => $priceType,
                "PRICE" => $priceValue,
                "CURRENCY" => "RUB"
            ]);
        }
    }

    public function run()
    {
        ProductDataTable::create();
        ProductDataTable::deleteAll();

        $this->print("[PROCESS] fillFrom\n");
        $this->fillFromData();
        $this->print("[PROCESS] fillTo\n");
        $this->fillToData();

        ProductDataTable::clear();
        $this->print("[PROCESS] merge\n");
        foreach ($this->iterator() as $row)
        {
            $this->merge($row);
        }
    }



}