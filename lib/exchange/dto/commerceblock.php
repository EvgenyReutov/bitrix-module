<?php


namespace Instrum\Main\Exchange\Dto;

use InvalidArgumentException;

class CommerceBlock
{
    /** @var string */
    protected $title;

    /** @var string */
    protected $category;

    /** @var string[] */
    protected $items;

    /**
     * CommerceBlock constructor.
     * @param string $title
     * @param string $category
     */
    public function __construct($title, $category)
    {
        if(empty($title)) {
            throw new InvalidArgumentException('Block title should be set');
        }

        $this->title = $title;
        $this->category = $category;
        $this->items = [];
    }

    /**
     * @param string $item
     */
    public function push($item)
    {
        $this->items[] = $item;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @return string[]
     */
    public function getItems()
    {
        return $this->items;
    }
}