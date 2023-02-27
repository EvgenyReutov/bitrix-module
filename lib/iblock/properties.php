<?php

namespace Instrum\Main\Iblock;

class Properties
{
    private $item;

    public function __construct($arProperty = false)
    {
        if($arProperty){
            $this->item = $arProperty;
        }
    }

    public function getValue(){
        return $this->item["VALUE"];
    }

    public function getField($field){
        if(!isset($this->item[$field])){
            throw new \Exception("неизвестное поле свойства [".$field."]");
        }
        return $this->item[$field];
    }
}