<?php


namespace Instrum\Main\Exchange\Dto;

use InvalidArgumentException;

class CommerceOffer
{
    /** @var string */
    protected $type;

    /** @var CommerceBlock[] */
    protected $items;

    /**
     * CommerceBlock constructor.
     * @param $type
     */
    public function __construct($type)
    {
        if(empty($type)) {
            throw new InvalidArgumentException('Offer type should be set');
        }

        $this->type = $type;
        $this->items = [];
    }

    /**
     * @param CommerceBlock $item
     */
    public function push($item)
    {
        $this->items[] = $item;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return CommerceBlock[]
     */
    public function getItems()
    {
        return $this->items;
    }
}