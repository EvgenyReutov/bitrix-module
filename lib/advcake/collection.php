<?php

namespace Instrum\Main\AdvCake;

class Collection
{
    protected $collection = [];

    public function addItem($id, $name, $price, $quantity = false, $categoryId = false, $categoryName = false)
    {
        $item = [$id, Entity::getString($name), $price ? $price : 0];

        if($quantity) $item[] = $quantity;
        if($categoryId) $item[] = $categoryId;
        if($categoryName) $item[] = Entity::getString($categoryName);

        $this->collection[] = $item;
        return $this;
    }

    public function isEmpty()
    {
        return !(bool)count($this->collection);
    }

    public function packToJS()
    {
        $jsBuffer = "new AdvCakeCollection()";

        foreach($this->collection as $arItem)
        {
            $jsBuffer .= ".addItem(".implode(", ", $arItem).")";
        }
        return $jsBuffer;
    }

    public function toArray()
    {
        return $this->collection;
    }
}