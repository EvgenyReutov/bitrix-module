<?php

namespace Instrum\Main\Pim;

use Bitrix\Main\DB\Connection;
use XMLWriter;

class Exporter
{
    /** @var Connection */
    protected $db;

    public function __construct($db)
    {
        if(empty($db)) {
            throw new \RuntimeException('Database connection should be specified');
        }

        $this->db = $db;
    }

    /**
     * @param string $filename
     */
    protected function checkFile($filename)
    {
        if(file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * @return XMLWriter
     */
    protected function createXMLWriter()
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->setIndent(true);
        return $xmlWriter;
    }

    protected function flushXmlToFile($xmlWriter, $filename)
    {
        file_put_contents($filename, $xmlWriter->flush(true), FILE_APPEND);
    }

    protected function flushXmlToScreen($xmlWriter)
    {
        echo $xmlWriter->flush(true);
    }

    protected function writeProductPrices($xmlWriter, $productUuid, $productData)
    {
        if(!empty($productData)) {
            $xmlWriter->startElement('product');
            $xmlWriter->writeAttribute('uuid', $productUuid);

            foreach ($productData as $priceType => $priceValues) {
                $xmlWriter->startElement('price');
                $xmlWriter->writeAttribute('type', $priceType);

                $xmlWriter->writeElement('value', $priceValues['value']);
                if(!empty($priceValues['old_value'])) {
                    $xmlWriter->writeElement('old_value', $priceValues['old_value']);
                }
                $xmlWriter->endElement(); // price
            }

            $xmlWriter->endElement(); // product
        }
    }

    protected function writeProductStocks($xmlWriter, $productUuid, $productData)
    {
        if(!empty($productData)) {
            $xmlWriter->startElement('product');
            $xmlWriter->writeAttribute('uuid', $productUuid);

            foreach ($productData as $WarehouseUid => $quantity) {
                $xmlWriter->startElement('stock');
                $xmlWriter->writeAttribute('warehouse', $WarehouseUid);
                $xmlWriter->text($quantity);
                $xmlWriter->endElement();
            }

            $xmlWriter->endElement(); // product
        }
    }

    public function prices($filename)
    {
        $this->checkFile($filename);

        $startTime = time();

        /** @var XMLWriter $xmlWriter */
        $xmlWriter = $this->createXMLWriter();
        $xmlWriter->startDocument('1.0', 'UTF-8');

        $xmlWriter->startElement('exchange');
        $xmlWriter->writeAttribute('date', date('Y-m-d H:i'));


        // Price types

        $rowset = $this->db->query("
            SELECT
                bcg.ID as id,
                bcg.NAME as name,
                bcg.XML_ID as uuid
            FROM
                b_catalog_group bcg
            WHERE 
                NOT LENGTH(COALESCE(bcg.XML_ID, '')) = 0
        ");

        $xmlWriter->startElement('price_types');
        while ($row = $rowset->fetch()) {
            $xmlWriter->startElement('price_type');
            $xmlWriter->writeAttribute('uuid', $row['uuid']);
            $xmlWriter->text($row['name']);
            $xmlWriter->endElement();
        }
        $xmlWriter->endElement(); //'price_types'


        // price values
        $rowset = $this->db->query("
            SELECT
                product.XML_ID AS product_uuid,
                pricetype.XML_ID AS price_type_uuid,
                price.PRICE AS value,
                property_old_price.VALUE as old_value
            FROM
                b_catalog_price price
                JOIN b_iblock_element product
                    ON product.ID = price.PRODUCT_ID
                    AND product.IBLOCK_ID = " . CATALOG_IB . "
                JOIN b_catalog_group pricetype
                    ON pricetype.ID = price.CATALOG_GROUP_ID
                LEFT JOIN b_iblock_element_property property_old_price
                    ON product.ID = property_old_price.IBLOCK_ELEMENT_ID
                    AND property_old_price.IBLOCK_PROPERTY_ID = 14050 /* old_price_prop */
            WHERE
                product.ACTIVE = 'Y'
                AND NOT LENGTH(COALESCE(pricetype.XML_ID, '')) = 0
            ORDER BY
                product.ID
        ");

        $xmlWriter->startElement('products');

        $currentProduct = '';
        $productData = [];
        $cnt = 0;

        while ($row = $rowset->fetch()) {
            if($currentProduct !== $row['product_uuid']) {
                $this->writeProductPrices($xmlWriter, $currentProduct, $productData);
                $currentProduct = $row['product_uuid'];
                $productData = [];
            }

            ++$cnt;
            if ($cnt >= 1000) {
                $this->flushXmlToFile($xmlWriter, $filename);
            }

            $productData[$row['price_type_uuid']] = [
                'value' => $row['value'],
                'old_value' => $row['old_value']
            ];
        }
        $this->writeProductPrices($xmlWriter, $currentProduct, $productData);
        $xmlWriter->endElement(); //'products'


        $xmlWriter->writeComment('generated in ' . (time() - $startTime) . ' s');
        $xmlWriter->endDocument();

        $this->flushXmlToFile($xmlWriter, $filename);
    }



    public function stocks($filename)
    {
        $this->checkFile($filename);

        $startTime = time();

        /** @var XMLWriter $xmlWriter */
        $xmlWriter = $this->createXMLWriter();
        $xmlWriter->startDocument('1.0', 'UTF-8');

        $xmlWriter->startElement('exchange');
        $xmlWriter->writeAttribute('date', date('Y-m-d H:i'));


        // Warehouses

        $rowset = $this->db->query("
            SELECT
                bcs.ID,
                bcs.TITLE as name,
                bcs.XML_ID as uuid
            FROM
                b_catalog_store bcs
            WHERE
                ACTIVE = 'Y'
                AND NOT XML_ID = ''
        ");

        $xmlWriter->startElement('warehouses');
        while ($row = $rowset->fetch()) {
            $xmlWriter->startElement('warehouse');
            $xmlWriter->writeAttribute('uuid', $row['uuid']);
            $xmlWriter->text($row['name']);
            $xmlWriter->endElement();
        }
        $xmlWriter->endElement(); //'warehouses'


        // price values
        $rowset = $this->db->query("
            SELECT
                bie.XML_ID AS product_uuid,
                bcs.XML_ID AS warehouse_uuid,
                bcsp.AMOUNT as quantity
            FROM
                b_catalog_store_product bcsp
                JOIN b_catalog_store bcs
                    ON bcs.ID = bcsp.STORE_ID
                JOIN b_iblock_element bie
                    ON bie.ID = bcsp.PRODUCT_ID
                    AND bie.IBLOCK_ID = " . CATALOG_IB . "
            WHERE
                NOT bcs.XML_ID = ''
            ORDER BY
                bie.ID
        ");

        $xmlWriter->startElement('products');

        $currentProduct = '';
        $productData = [];
        $cnt = 0;

        while ($row = $rowset->fetch()) {
            if($currentProduct !== $row['product_uuid']) {
                $this->writeProductStocks($xmlWriter, $currentProduct, $productData);
                $currentProduct = $row['product_uuid'];
                $productData = [];
            }

            ++$cnt;
            if ($cnt >= 1000) {
                $this->flushXmlToFile($xmlWriter, $filename);
            }

            $productData[$row['warehouse_uuid']] = $row['quantity'];
        }
        $this->writeProductStocks($xmlWriter, $currentProduct, $productData);
        $xmlWriter->endElement(); //'products'


        $xmlWriter->writeComment('generated in ' . (time() - $startTime) . ' s');
        $xmlWriter->endDocument();

        $this->flushXmlToFile($xmlWriter, $filename);
    }

    public function products($filename)
    {
        $this->checkFile($filename);

        $startTime = time();

        /** @var XMLWriter $xmlWriter */
        $xmlWriter = $this->createXMLWriter();
        $xmlWriter->startDocument('1.0', 'UTF-8');

        $xmlWriter->startElement('exchange');
        $xmlWriter->writeAttribute('date', date('Y-m-d H:i'));


        $rowset = $this->db->query("
            SELECT
                bie.ID,
                bie.CODE,
                bie.XML_ID
            FROM
                b_iblock_element bie
            WHERE
                bie.ACTIVE = 'Y'
                AND bie.IBLOCK_ID = " . CATALOG_IB . "
        ");

        $cnt = 0;

        $xmlWriter->startElement('products');
        while ($row = $rowset->fetch()) {

            $xmlWriter->startElement('product');
            $xmlWriter->writeAttribute('uuid', $row['XML_ID']);
            $xmlWriter->writeElement('id', $row['ID']);
            $xmlWriter->writeElement('url', '/product/' . $row['CODE'] . '/');
            $xmlWriter->endElement();

            ++$cnt;
            if ($cnt >= 1000) {
                $this->flushXmlToFile($xmlWriter, $filename);
            }
        }
        $xmlWriter->endElement();

        $xmlWriter->endElement();
        $xmlWriter->writeComment('generated in ' . (time() - $startTime) . ' s');
        $xmlWriter->endDocument();

        $this->flushXmlToFile($xmlWriter, $filename);
    }
}