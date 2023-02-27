<?php


namespace Instrum\Main\Exchange\Dto;


use RuntimeException;

class Product
{
    public $uuid;
    public $name;
    public $barcode;
    public $sku;

    public $length;
    public $width;
    public $height;
    public $weight;

    public $description;
    public $categories;
    public $featureValues;



    public function __construct($uuid, $name, $barcode, $sku, $length, $width, $height, $weight, $description)
    {
        if(empty($uuid)) {
            throw new RuntimeException('Product UUID should not be empty');
        }
        if(empty($name)) {
            throw new RuntimeException('Product name should not be empty');
        }

        $this->uuid = $uuid;
        $this->name = $name;

        $this->barcode = $barcode;
        $this->sku = $sku;
        $this->length = $length;
        $this->width = $width;
        $this->height = $height;
        $this->weight = $weight;
        $this->description = $description;

        $this->categories = [];
        $this->featureValues = [];
    }
}