<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class FeatureValue
{
    public $uuid;
    public $name;

    public function __construct($uuid, $name)
    {
        if(empty($uuid)) {
            throw new RuntimeException('Feature value UUID should not be empty');
        }

        $this->uuid = $uuid;
        $this->name = $name;
    }
}