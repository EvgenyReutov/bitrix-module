<?php
namespace Instrum\Main\Iblock\Element;

/**
 * Элемент инфоблока
 * Class Item
 * @package Instrum\Main\Iblock\Element
 */
class Item
{
    private $data = [];

    /**
     * Item constructor.
     * @param array $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return array_keys($this->data);
    }

    /**
     * @param $key
     * @return mixed
     * @throws \Exception
     */
    public function getField($key)
    {
        return $this->data[$key];
    }

    /**
     * @return integer
     * @throws \Exception
     */
    public function getId()
    {
        return $this->getField('ID');
    }

    public function getProperty($code, $format = false)
    {
        $VALUES = array();
        $res = \CIBlockElement::GetProperty($this->getField('IBLOCK_ID'), $this->getId(), [], array("CODE" => $code));
        while ($prop = $res->GetNext())
        {
            $VALUES[] = $format ? $prop['~VALUE'] : $prop['VALUE'];
        }
        return !$VALUES || sizeof($VALUES) == 1 ? array_shift($VALUES) : $VALUES;
    }

    /**
     * @param array $arFields
     * @return $this
     */
    public function setFields($arFields=[])
    {
        $this->data = array_merge($this->data, $arFields);
        return $this;
    }

    private function prepareData()
    {
        foreach($this->data as $key=>$value)
        {
            if(strpos($key, '~') !== False) unset($this->data[$key]);
        }
    }

    /**
     * @return bool|int
     * @throws \Exception
     */
    public function save()
    {
        $this->prepareData();

        $el = new \CIBlockElement();
        if($this->getId())
        {
            if($res = $el->Update($this->getId(), $this->data))
                return $res;
        }
        else
        {
            if($ID = $el->Add($this->data))
                return $ID;
        }

        throw new \Exception($el->LAST_ERROR);
    }

}
