<?php

namespace Instrum\Main\AdvCake;

use Instrum\Main\AdvCake\Entity;

class Section extends Entity{
    protected $pageType = 3;

    protected $category;
    /**
     * @var Collection
     */
    protected $products;

    public function setCategory($id, $name)
    {
        $this->category = [$id, self::getString($name)];
        return $this;
    }

    public function setProducts($collection)
    {
        $this->products = $collection;
        return $this;
    }

    public function get(&$jsBuffer)
    {
        parent::get($jsBuffer);

        if($this->category)
        {
            $jsBuffer .= ".setCurrentCategory(".implode(", ", $this->category).")";
        }

        if($this->products && !$this->products->isEmpty())
        {
            $jsBuffer .= ".setProducts(".$this->products->packToJS().")";
        }

        return true;
    }

}