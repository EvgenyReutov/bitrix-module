<?php


namespace Instrum\Main;


use Bitrix\Main\DB\Connection;
use \RuntimeException;
use \Exception;

class LwhPropertyUpdater
{
    /** @var Connection */
    protected $db;
    /** @var int */
    protected $iblockId;

    /**
     * LwhPropertyUpdater constructor.
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
     * @param string $uuid
     * @param string $param
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function updateProperty($uuid, $param)
    {
        $query = "
            UPDATE
                b_catalog_product product
                INNER JOIN b_iblock_element ibe
                    ON ibe.ID = product.ID
                LEFT JOIN b_iblock_property feature
                    ON feature.XML_ID = '{$uuid}'
                LEFT JOIN b_iblock_element_property property
                    ON property.IBLOCK_ELEMENT_ID = ibe.ID
                    AND property.IBLOCK_PROPERTY_ID = feature.ID
            SET
                {$param} = property.VALUE * 1000
            WHERE
                ibe.IBLOCK_ID = {$this->iblockId}
                AND NOT property.VALUE IS NULL
        ";
        $this->db->query($query);
    }

    /**
     *
     */
    public function run()
    {
        $properties = [
            'LENGTH' => '654d7e07-4632-11e9-80c6-0cc47a303246',
            'WIDTH' => '85876052-4632-11e9-80c6-0cc47a303246',
            'HEIGTH' => '932ed8c7-4632-11e9-80c6-0cc47a303246',
        ];

        foreach ($properties as $productParam => $propertyUuid) {
            try {
                $this->updateProperty($propertyUuid, $productParam);
            } catch (Exception $e) {

            }
        }
    }
}