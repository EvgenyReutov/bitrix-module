<?php


namespace Instrum\Main\Exchange\Reader;

use Instrum\Main\Exchange\Dto;
use Instrum\Main\Exchange\PriceInfoReaderInterface;
use SimpleXMLElement;

class OneCPriceInfoReader extends AbstractXmlReader implements PriceInfoReaderInterface
{
    public const TAG_PRICE_TYPES = 'price_types';
    public const TAG_PRICE_TYPE = 'price_type';
    public const ATTRIBUTE_PRICE_TYPE_UUID = 'uuid';

    public const TAG_PRODUCTS = 'products';
    public const TAG_PRODUCT = 'product';
    public const ATTRIBUTE_PRODUCT_UUID = 'uuid';
    public const TAG_PRODUCT_PRICE = 'price';
    public const ATTRIBUTE_PRODUCT_PRICE_TYPE_UUID = 'type';
    public const TAG_PRODUCT_PRICE_VALUE = 'value';
    public const TAG_PRODUCT_PRICE_OLD_VALUE = 'old_value';

    /**
     * @inheritDoc
     */
    public function readPriceTypes()
    {
        $reader = $this->getFileReader();

        /** @noinspection MissingOrEmptyGroupStatementInspection */
        /** @noinspection PhpStatementHasEmptyBodyInspection */
        /** @noinspection LoopWhichDoesNotLoopInspection */
        while ($reader->read() && $reader->name !== self::TAG_PRICE_TYPES) {
        }

        $xml = new SimpleXMLElement($reader->readOuterXml());
        foreach ($xml->{self::TAG_PRICE_TYPE} as $xmlPriceType) {
            yield new Dto\PriceType(
                (string)$xmlPriceType->attributes()->{self::ATTRIBUTE_PRICE_TYPE_UUID},
                (string)$xmlPriceType
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function readPrices()
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
            $container = new dto\PriceContainer($productUuid);

            foreach ($xmlProduct->{self::TAG_PRODUCT_PRICE} as $xmlPrice) {
                $container->push(
                    new dto\Price(
                        $productUuid,
                        (string)$xmlPrice->attributes()->{self::ATTRIBUTE_PRODUCT_PRICE_TYPE_UUID},
                        (float)$xmlPrice->{self::TAG_PRODUCT_PRICE_VALUE},
                        (float)$xmlPrice->{self::TAG_PRODUCT_PRICE_OLD_VALUE}
                    )
                );
            }

            yield $container;

            $reader->next(self::TAG_PRODUCT);
        }
    }
}