<?php

namespace Instrum\Main\AdvCake;

use Instrum\Main\AdvCake\Entity;

class Product extends Entity{
    protected $pageType = 2;

    protected $category;
    protected $product;

    public function setCategory($id, $name)
    {
        $this->category = [$id, self::getString($name)];
        return $this;
    }

    public function setProduct($id, $name, $price)
    {
        $this->product = [$id, self::getString($name), $price];
        return $this;
    }

    public function get(&$jsBuffer)
    {
        parent::get($jsBuffer);

        if($this->category)
        {
            $jsBuffer .= ".setCurrentCategory(".implode(", ", $this->category).")";
        }

        if($this->product)
        {
            $jsBuffer .= ".setCurrentProduct(".implode(", ", $this->product).")";
        }

        return true;
    }

}