<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class Warehouse
{
    public $uuid;
    public $name;

    public function __construct($uuid, $name)
    {
        if (empty($uuid)) {
            throw new RuntimeException('Warehouse UUID should not be empty');
        }
        if (empty($name)) {
            throw new RuntimeException('Warehouse name should not be empty');
        }

        $this->uuid = $uuid;
        $this->name = $name;
    }
}