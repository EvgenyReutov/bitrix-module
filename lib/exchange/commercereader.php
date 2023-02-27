<?php


namespace Instrum\Main\Exchange;

use Generator;
use Instrum\Main\Exchange\Dto;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;

class CommerceReader
{
    const TAG_PRODUCTS = 'products';
    const TAG_PRODUCT = 'product';
    const ATTRIBUTE_PRODUCT_UUID = 'uuid';
    const TAG_COMMERCE = 'commerce';
    const TAG_OFFER = 'offer';
    const ATTRIBUTE_OFFER_TYPE = 'type';
    const TAG_BLOCK = 'block';
    const TAG_BLOCK_NAME = 'name';
    const TAG_BLOCK_CATEGORY = 'category';


    /** @var string */
    protected $filename;

    public function __construct(string $filename)
    {
        if (!is_readable($filename)) {
            throw new RuntimeException('File ' . $filename . ' is not readable');
        }
        $this->filename = $filename;
    }

    /**
     * @return XMLReader
     */
    protected function getFileReader()
    {
        $reader = new XMLReader();
        if (!$reader->open($this->filename)) {
            throw new RuntimeException('Cannot open ' . $this->filename . ' for XML reading');
        }
        return $reader;
    }

    /**
     * @return Generator
     */
    public function read()
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

            if ($xmlProduct->{self::TAG_COMMERCE}) {
                $productOffers = new Dto\CommerceOffers($productUuid);

                foreach ($xmlProduct->{self::TAG_COMMERCE}->{self::TAG_OFFER} as $xmlOffer) {
                    if($xmlOffer->{self::TAG_BLOCK}->count()) {
                        $offer = new Dto\CommerceOffer((string)$xmlOffer->attributes()->{self::ATTRIBUTE_OFFER_TYPE});
                        foreach ($xmlOffer->{self::TAG_BLOCK} as $xmlBlock) {
                            $block = new Dto\CommerceBlock((string)$xmlBlock->{self::TAG_BLOCK_NAME}, (string)$xmlBlock->{self::TAG_BLOCK_CATEGORY});
                            foreach ($xmlBlock->{self::TAG_PRODUCTS}->{self::TAG_PRODUCT} as $xmlOfferProduct) {
                                $block->push((string) $xmlOfferProduct);
                            }
                            $offer->push($block);
                        }
                        $productOffers->push($offer);
                    }
                }

                if($productOffers->getItemsCount()) {
                    yield $productOffers;
                }
            }

            $reader->next(self::TAG_PRODUCT);
        }
    }
}