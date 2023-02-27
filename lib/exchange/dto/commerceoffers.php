<?php


namespace Instrum\Main\Exchange\Dto;

use InvalidArgumentException;

class CommerceOffers
{
    /** @var string */
    protected $product_uuid;

    /** @var CommerceOffer[] */
    protected $items;

    /**
     * CommerceBlock constructor.
     * @param $type
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
     * @param CommerceOffer $item
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
     * @return CommerceOffer[]
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function getItemsCount()
    {
        return count($this->items);
    }
}