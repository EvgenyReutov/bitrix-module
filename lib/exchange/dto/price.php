<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class Price
{
    public $product_uuid;
    public $type_uuid;
    public $value;
    public $old_value;

    public function __construct(string $product_uuid, string $type_uuid, float $value, ?float $old_value)
    {
        if (empty($product_uuid)) {
            throw new RuntimeException('Product UUID should not be empty');
        }
        if (empty($type_uuid)) {
            throw new RuntimeException('Price type UUID should not be empty');
        }

        $this->product_uuid = $product_uuid;
        $this->type_uuid = $type_uuid;
        $this->value = $value;
        $this->old_value = $old_value;
    }
}