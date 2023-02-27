<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class Stock
{
    public $product_uuid;
    public $warehouse_uuid;
    public $value;

    public function __construct($product_uuid, $warehouse_uuid, $value)
    {
        if (empty($product_uuid)) {
            throw new RuntimeException('Product UUID should not be empty');
        }
        if (empty($warehouse_uuid)) {
            throw new RuntimeException('Warehouse UUID should not be empty');
        }

        $this->product_uuid = $product_uuid;
        $this->warehouse_uuid = $warehouse_uuid;
        $this->value = $value;
    }
}