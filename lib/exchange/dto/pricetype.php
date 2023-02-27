<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class PriceType
{
    public $uuid;
    public $name;

    public function __construct($uuid, $name)
    {
        if (empty($uuid)) {
            throw new RuntimeException('Price type UUID should not be empty');
        }
        if (empty($name)) {
            throw new RuntimeException('Price type name should not be empty');
        }

        $this->uuid = $uuid;
        $this->name = $name;
    }
}