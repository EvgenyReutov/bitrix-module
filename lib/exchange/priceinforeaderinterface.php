<?php


namespace Instrum\Main\Exchange;

use Generator;

interface PriceInfoReaderInterface
{
    /**
     * PriceInfoReaderInterface constructor.
     * @param string $fileData
     * @param bool $isUri
     */
    public function __construct($fileData, $isUri = false);

    /**
     * @return Generator
     */
    public function readPriceTypes();

    /**
     * @return Generator
     */
    public function readPrices();
}