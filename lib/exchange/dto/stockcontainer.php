<?php


namespace Instrum\Main\Exchange\Dto;


class StockContainer
{
    /** @var string */
    protected $product_uuid;
    /** @var Stock[] */
    protected $items;

    /**
     * PriceContainer constructor.
     * @param string $product_uuid
     */
    public function __construct($product_uuid)
    {
        if(empty($product_uuid)) {
            throw new \InvalidArgumentException('Product UUID should not be empty');
        }

        $this->product_uuid = $product_uuid;
        $this->items = [];
    }

    /**
     *
     */
    public function __destruct()
    {
        unset($this->items);
        unset($this->product_uuid);
    }

    /**
     * @param Stock $item
     */
    public function push($item)
    {
        $this->items[] = $item;
    }

    /**
     * @return string
     */
    public function getProductUuid()
    {
        return $this->product_uuid;
    }

    /**
     * @return Stock[]
     */
    public function getItems()
    {
        return $this->items;
    }
}