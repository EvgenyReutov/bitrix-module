<?php


namespace Instrum\Main\Exchange;

use Generator;

interface StockInfoReaderInterface
{
    /**
     * StockInfoReaderInterface constructor.
     * @param string $fileData
     * @param bool $isUri
     */
    public function __construct($fileData, $isUri = false);

    /**
     * @return Generator
     */
    public function readWarehouses();

    /**
     * @return Generator
     */
    public function readStocks();
}