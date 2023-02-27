<?php

namespace Instrum\Main\Exchange;

use Bitrix\Main\DB\Connection;
use Instrum\Main\Exchange\Dto;
use \CIBlockElement;

class CommerceService implements ExchangeServiceInterface
{
    const IBLOCK_ID = 20;
    const FEATURE_NAME = 'COMMERCE_BLOCKS';

    /** @var Connection */
    protected $db;

    /** @var CommerceReader */
    protected $reader;

    /**
     * CommerceService constructor.
     * @param Connection $db
     * @param CommerceReader $reader
     */
    public function __construct($db, $reader)
    {
        if (empty($db)) {
            throw new \RuntimeException('Database connection should be specified');
        }
        if (empty($reader)) {
            throw new \RuntimeException('Data reader should be specified');
        }

        $this->db = $db;
        $this->reader = $reader;
    }


    protected function getProductByUUid($uuid)
    {
        return CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => self::IBLOCK_ID, 'XML_ID' => $uuid],
            false,
            false,
            [
                'ID',
                'IBLOCK_ID'
            ]
        )->Fetch();
    }

    protected function getProductUuidMap($uuids)
    {
        $result = [];

        if (!empty($uuids)) {
            $rowset = CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => self::IBLOCK_ID, 'XML_ID' => $uuids],
                false,
                false,
                [
                    'ID',
                    'XML_ID'
                ]
            );
            while ($row = $rowset->Fetch()) {
                $result[$row['XML_ID']] = (int)$row['ID'];
            }
        }

        return $result;
    }

    protected function getCategoryUuidMap($uuids)
    {
        $result = [];

        if (!empty($uuids)) {
            $rowset = \CIBlockSection::GetList(
                [],
                [
                    'IBLOCK_ID' => self::IBLOCK_ID,
                    'XML_ID' => $uuids
                ],
                false,
                [
                    'ID',
                    'XML_ID'
                ],
                false
            );
            while ($row = $rowset->Fetch()) {
                $result[$row['XML_ID']] = (int)$row['ID'];
            }
        }
        return $result;
    }

    public function run()
    {
        /** @var Dto\CommerceOffers $productOffers */
        foreach ($this->reader->read() as $productOffers) {
            $product = $this->getProductByUUid($productOffers->getProductUuid());
            if ($product) {
                $commerce = [];

                $productUuids = [];
                $categoryUuids = [];
                /** @var Dto\CommerceOffer $offer */
                foreach ($productOffers->getItems() as $offer) {
                    /** @var Dto\CommerceBlock $block */
                    foreach ($offer->getItems() as $block) {
                        $productUuids[] = $block->getItems();
                        $blockCategory = $block->getCategory();
                        if(!empty($blockCategory)) {
                            $categoryUuids[] = $blockCategory;
                        }
                    }
                }
                $productUuids = array_unique(array_filter(array_merge(...$productUuids)));
                $productUuids = $this->getProductUuidMap($productUuids);

                $categoryUuids = array_unique($categoryUuids);
                $categoryUuids = $this->getCategoryUuidMap($categoryUuids);


                if (!empty($productUuids)) {
                    foreach ($productOffers->getItems() as $offer) {
                        $productOffer = [
                            'type' => $offer->getType(),
                            'blocks' => []
                        ];
                        foreach ($offer->getItems() as $block) {
                            $productBlock = [
                                'name' => $block->getTitle(),
                                'products' => []
                            ];

                            if (!empty($block->getCategory()) && !empty($categoryUuids[$block->getCategory()])) {
                                $productBlock['category'] = $categoryUuids[$block->getCategory()];
                            }

                            foreach ($block->getItems() as $item) {
                                if (isset($productUuids[$item])) {
                                    $productBlock['products'][] = $productUuids[$item];
                                }
                            }
                            if (!empty($productBlock['products'])) {
                                $productOffer['blocks'][] = $productBlock;
                            }
                        }
                        if (!empty($productOffer['blocks'])) {
                            $commerce[] = $productOffer;
                        }
                    }
                }

                if(!empty($commerce)) {
                    CIBlockElement::SetPropertyValues(
                        $product['ID'],
                        $product['IBLOCK_ID'],
                        json_encode($commerce, JSON_UNESCAPED_UNICODE),
                        self::FEATURE_NAME
                    );
                }
            }
        }
    }
}