<?php


namespace Instrum\Main\Exchange;

use Bitrix\Catalog\StoreProductTable;
use Bitrix\Catalog\StoreTable;
use Instrum\Main\Exchange\Dto;
use Instrum\Main\RegionPropertyFusion;
use \CCatalogGroup;
use \CIBlockElement;
use \RuntimeException;
use Bitrix\Main\Application;

class StockInfoExchangeService
{
    /** @var StockInfoReaderInterface */
    protected $reader;

    /**
     * StockInfoExchangeService constructor.
     * @param $reader
     */
    public function __construct($reader)
    {
        $this->reader = $reader;
    }

    protected function getConnection()
    {
        return Application::getConnection();
    }

    protected function getProductByUUid($uuid)
    {
        return CIBlockElement::GetList([], ['IBLOCK_ID' => CATALOG_IB, 'XML_ID' => $uuid])->Fetch();
    }

    protected function getWarehouseByUuid($uuid)
    {
        return StoreTable::getList(['filter' => ['XML_ID' => $uuid]])->fetch();
    }

    protected function getFusion()
    {
        $fusion = new RegionPropertyFusion(CATALOG_IB);
        $regionStoresMap = $fusion->getRegionStoresMap();
        $fusion->checkProperties($regionStoresMap);
        return $fusion;
    }


    public function importWarehouses()
    {
        /** @var Dto\Warehouse $dtoWarehouse */
        foreach ($this->reader->readWarehouses() as $dtoWarehouse) {
            $warehouse = $this->getWarehouseByUuid($dtoWarehouse->uuid);
            if (!$warehouse && !StoreTable::add(
                    [
                        'XML_ID' => $dtoWarehouse->uuid,
                        'TITLE' => $dtoWarehouse->name,
                        'ADDRESS' => ' ',
                    ]
                )->isSuccess()) {
                throw new RuntimeException('Cannot add new warehouse ' . $dtoWarehouse->uuid);
            }
        }
    }

    protected function getWarehouseMap()
    {
        $result = [];
        $rowset = StoreTable::getList(['select' => ['ID', 'XML_ID']]);
        while ($row = $rowset->fetch()) {
            $result[$row['XML_ID']] = $row['ID'];
        }
        return $result;
    }

    protected function getProductStocks($productId)
    {
        /** @var Connection $db */
        $db = $this->getConnection();
        $result = [];

        $rowset = $db->query("
            SELECT
                bcs.XML_ID,
                bcsp.ID,
                bcsp.AMOUNT
            FROM
                b_catalog_store_product bcsp
                JOIN b_catalog_store bcs
                    ON bcs.ID = bcsp.STORE_ID
            WHERE
                bcsp.PRODUCT_ID = $productId                
        ");


        while ($row = $rowset->fetch()) {
            $result[$row['XML_ID']] = [
                'id' => $row['ID'],
                'value' => (int)$row['AMOUNT'],
            ];
        }

        return $result;
    }

    public function importStocks()
    {
        $warehouseMap = $this->getWarehouseMap();
        $productUuid = null;
        $product = null;

        $fusion = $this->getFusion();

        /** @var Dto\StockContainer $dtoStockContainer */
        foreach ($this->reader->readStocks() as $dtoStockContainer) {
            $product = $this->getProductByUUid($dtoStockContainer->getProductUuid());

            if($product) {
                $productStocks = $this->getProductStocks($product['ID']);
                $recalcAvailability = false;

                /** @var Dto\Stock $dtoStock */
                foreach ($dtoStockContainer->getItems() as $dtoStock) {
                    if(isset($productStocks[$dtoStock->warehouse_uuid])) {
                        if ($productStocks[$dtoStock->warehouse_uuid]['value'] !== $dtoStock->value) {
                            $recalcAvailability = true;

                            $query = StoreProductTable::update(
                                $productStocks[$dtoStock->warehouse_uuid]['id'],
                                ['AMOUNT' => $dtoStock->value]
                            );

                            if(!$query->isSuccess()) {
                                throw new RuntimeException(
                                    'Cannot update stock for product ' . $dtoStock->product_uuid . ' with warehouse ' . $dtoStock->warehouse_uuid
                                );
                            }
                        }
                    } else {
                        $recalcAvailability = true;
                        $query = StoreProductTable::add(
                            [
                                'PRODUCT_ID' => $product['ID'],
                                'STORE_ID' => $warehouseMap[$dtoStock->warehouse_uuid],
                                'AMOUNT' => $dtoStock->value
                            ]
                        );
                        if(!$query->isSuccess()) {
                            $err = implode('; ', $query->getErrorMessages());
                            throw new RuntimeException(
                                'Cannot add stock for product ' . $dtoStock->product_uuid . ' with warehouse ' . $dtoStock->warehouse_uuid . ': ' . $err
                            );
                        }
                    }
                }


                if($recalcAvailability) {
                    $fusion->processAvailability($product['ID']);
                }
            }
        }
    }
}