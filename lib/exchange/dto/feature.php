<?php

namespace Instrum\Main\Exchange\Dto;

use \RuntimeException;

class Feature
{
    const TYPE_ENUM = 1;
    const TYPE_STRING = 2;
    const TYPE_NUMERIC = 3;
    const TYPE_BOOLEAN = 4;

    public $uuid;
    public $type;
    public $name;
    public $enum;

    public function getTypes()
    {
        return [
            self::TYPE_ENUM,
            self::TYPE_STRING,
            self::TYPE_NUMERIC,
            self::TYPE_BOOLEAN
        ];
    }

    public function __construct($uuid, $type, $name)
    {
        if(empty($uuid)) {
            throw new RuntimeException('Feature UUID should not be empty');
        }
        if(empty($type)) {
            throw new RuntimeException('Feature type should not be empty');
        }
        if(!in_array($type, $this->getTypes())) {
            throw new RuntimeException('Wrong feature type specified');
        }

        if(empty($name)) {
            throw new RuntimeException('Feature name should not be empty');
        }

        $this->uuid = $uuid;
        $this->type = $type;
        $this->name = $name;
        $this->enum = [];
    }

}