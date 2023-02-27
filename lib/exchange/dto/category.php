<?php

namespace Instrum\Main\Exchange\Dto;

use \RuntimeException;

class Category
{
    public $uuid;
    public $parent_uuid;
    public $name;

    public function __construct($uuid, $parent_uuid, $name)
    {
        if(empty($uuid)) {
            throw new RuntimeException('Category UUID should not be empty');
        }
        if(empty($name)) {
            throw new RuntimeException('Category name should not be empty');
        }

        $this->uuid = $uuid;
        $this->parent_uuid = $parent_uuid;
        $this->name = $name;
    }
}