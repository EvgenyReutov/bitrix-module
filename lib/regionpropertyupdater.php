<?php


namespace Instrum\Main;


use Bitrix\Main\DB\Connection;
use RuntimeException;
use Sotbit\Regions\Internals\RegionsTable;

class RegionPropertyUpdater
{
    /** @var Connection */
    protected $db;

    /** @var int */
    protected $iblockId;


    /**
     * RegionPropertyUpdater constructor.
     * @param Connection $db
     * @param int $iblockId
     */
    public function __construct($db, $iblockId)
    {
        if(empty($db)) {
            throw new RuntimeException('DB connection is not defined');
        }
        if(empty($iblockId)) {
            throw new RuntimeException('Iblock ID should not be empty');
        }

        $this->db = $db;
        $this->iblockId = $iblockId;
    }

    /**
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    protected function getRegionStoresMap()
    {
        $regionStoresMap = [];

        $regions = RegionsTable::GetList([
            'select' => ['ID','STORE'],
        ])->fetchAll();

        foreach ($regions as $region) {
            if(!empty($region['STORE'])) {
                $regionStoresMap[$region['ID']] = $region['STORE'];
            }
        }

        return $regionStoresMap;
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\Db\SqlQueryException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function run()
    {
        $regionStoresMap = $this->getRegionStoresMap();
        $fusion = new RegionPropertyFusion($this->iblockId);
        $fusion->checkProperties($regionStoresMap);

        $rowset = $this->db->query("
            SELECT
                p.ID
            FROM
                b_iblock_element p                
            WHERE
                p.IBLOCK_ID = " . $this->iblockId . "
            ORDER BY
                p.ID
        ");

        while ($row = $rowset->fetch()) {
            $fusion->processAvailability($row['ID']);
        }
    }
}