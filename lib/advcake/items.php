<?php

namespace Instrum\Main\AdvCake;

use Instrum\Main\AdvCake\Entity;

class Items extends Entity{
    protected $pageType = 7;
    /**
     * @var Collection
     */
    protected $products;

    public function setProducts($collection)
    {
        $this->products = $collection;
        return $this;
    }

    public function get(&$jsBuffer)
    {
        parent::get($jsBuffer);

        if($this->products && !$this->products->isEmpty())
        {
            $jsBuffer .= ".setProducts(".$this->products->packToJS().")";
        }

        return true;
    }

}