<?php


namespace Instrum\Main\Exchange\Reader;

use Instrum\Main\Exchange\Dto;
use Instrum\Main\Exchange\StockInfoReaderInterface;
use SimpleXMLElement;

class OneCStockInfoReader extends AbstractXmlReader implements StockInfoReaderInterface
{
    public const TAG_WAREHOUSES = 'warehouses';
    public const TAG_WAREHOUSE = 'warehouse';
    public const ATTRIBUTE_WAREHOUSE_UUID = 'uuid';

    public const TAG_PRODUCTS = 'products';
    public const TAG_PRODUCT = 'product';
    public const ATTRIBUTE_PRODUCT_UUID = 'uuid';

    public const TAG_PRODUCT_STOCK = 'stock';
    public const ATTRIBUTE_PRODUCT_STOCK_WAREHOUSE_UUID = 'warehouse';

    /**
     * @inheritDoc
     */
    public function readWarehouses()
    {
        $reader = $this->getFileReader();

        /** @noinspection MissingOrEmptyGroupStatementInspection */
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection LoopWhichDoesNotLoopInspection */
        while ($reader->read() && $reader->name !== self::TAG_WAREHOUSES) {
        }

        $xml = new SimpleXMLElement($reader->readOuterXml());
        foreach ($xml->{self::TAG_WAREHOUSE} as $xmlWarehouse) {
            yield new Dto\Warehouse(
                (string)$xmlWarehouse->attributes()->{self::ATTRIBUTE_WAREHOUSE_UUID},
                (string)$xmlWarehouse
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function readStocks()
    {
        $reader = $this->getFileReader();

        /** @noinspection MissingOrEmptyGroupStatementInspection */
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection LoopWhichDoesNotLoopInspection */
        while ($reader->read() && $reader->name !== self::TAG_PRODUCTS) {
        }

        /** @noinspection MissingOrEmptyGroupStatementInspection */
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection LoopWhichDoesNotLoopInspection */
        while ($reader->read() && $reader->name !== self::TAG_PRODUCT) {
        }

        while ($reader->name === self::TAG_PRODUCT) {
            $xmlProduct = new SimpleXMLElement($reader->readOuterXml());

            $productUuid = (string)$xmlProduct->attributes()->{self::ATTRIBUTE_PRODUCT_UUID};
            $container = new Dto\StockContainer($productUuid);

            foreach ($xmlProduct->{self::TAG_PRODUCT_STOCK} as $xmlStock) {
                $container->push(
                    new Dto\Stock(
                        $productUuid,
                        (string)$xmlStock->attributes()->{self::ATTRIBUTE_PRODUCT_STOCK_WAREHOUSE_UUID},
                        (int)$xmlStock
                    )
                );
            }

            yield $container;

            $reader->next(self::TAG_PRODUCT);
        }
    }
}