<?php


namespace Instrum\Main\Exchange;

use Bitrix\Main\DB\Connection;
use \Exception;
use \RuntimeException;
use ZipArchive;

class JobManager
{
    const TABLE_NAME = 'stuff_exchange_job';

    const STATUS_NEW = 1;
    const STATUS_WORK = 2;
    const STATUS_COMPLETE = 3;
    const STATUS_ERROR = 10;


    const TYPE_1C_EXCHANGE = 1;
    const TYPE_UTKA_EXCHANGE = 2;
    const TYPE_UTKA_COMMERCE = 3;


    /** @var Connection */
    protected $db;


    /**
     * JobManager constructor.
     * @param Connection $db
     */
    public function __construct($db)
    {
        if (empty($db)) {
            throw new RuntimeException('DB connection not specified');
        }

        $this->db = $db;

        $this->checkJobTable();
    }

    protected function checkJobTable()
    {
        $this->db->queryExecute(
            "
            CREATE TABLE IF NOT EXISTS " . self::TABLE_NAME . " (
                id INT NOT NULL AUTO_INCREMENT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                status INT NOT NULL DEFAULT 1,
                type INT NOT NULL DEFAULT 1,
                filename VARCHAR(1024) NOT NULL,
                filesize BIGINT NULL,
                description VARCHAR(1024),
                PRIMARY KEY (id)
            );
        "
        );
    }

    /**
     * @param $filename
     * @return string|null
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function push($filename, $type)
    {
        $filesize = filesize($filename);
        if ($filesize === false) {
            $filesize = 'NULL';
        }
        $type = (int)$type;
        $this->db->queryExecute(
            "INSERT INTO " . self::TABLE_NAME . "(filename, filesize, type) VALUES ('" . $this->db->getSqlHelper(
            )->forSql($filename) . "', $filesize, $type)"
        );
        $id = $this->db->queryScalar("SELECT LAST_INSERT_ID()");
        return $id;
    }

    /**
     * @return array
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function shift()
    {
        $rowset = $this->db->query(
            "
            SELECT
                id,
                filename,
                type
            FROM
                " . self::TABLE_NAME . "
            WHERE
                status = " . self::STATUS_NEW . "
            ORDER BY
                created_at
        "
        );
        while ($row = $rowset->fetch()) {
            return $row;
        }
        return null;
    }

    /**
     * @param int $id
     * @param int $status
     * @param string $description
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function setStatus($id, $status, $description = '')
    {
        $this->db->query(
            "
            UPDATE " . self::TABLE_NAME . "
            SET
                updated_at = CURRENT_TIMESTAMP,
                status = $status,
                description = '" . $this->db->getSqlHelper()->forSql($description) . "'
            WHERE
                id = $id
        "
        );
    }

    /**
     * @param int $id
     * @return array|false|null
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function getStatus($id)
    {
        $rowset = $this->db->query(
            "
            SELECT
                status,
                description
            FROM
                " . self::TABLE_NAME . "
            WHERE
                id = $id
        "
        );

        $row = $rowset->fetch();
        if ($row) {
            return $row;
        }

        return null;
    }

    /**
     * @param $filename
     */
    protected function checkZip($filename)
    {
        $zip = new ZipArchive;
        $res = $zip->open($filename);
        if ($res === true) {
            $tfilename = $filename . '.uz';

            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $zfilename = $zip->getNameIndex($i);
                copy('zip://' . $filename . '#' . $zfilename, $tfilename);
                unlink($filename);
                rename($tfilename, $filename);
                break;
            }

            $zip->close();
        }
    }

    /**
     * @param $filename
     * @param $type
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    protected function run($filename, $type)
    {
        $this->checkZip($filename);

        /** @var ExchangeServiceInterface $service */
        $service = null;

        switch ($type) {
            case self::TYPE_1C_EXCHANGE:
                $service = new Service($this->db, new Reader($filename));
                break;
            case self::TYPE_UTKA_COMMERCE:
                $service = new CommerceService($this->db, new CommerceReader($filename));
                break;
        }

        $service->run();
    }

    /**
     * @return bool
     * @throws \Bitrix\Main\Db\SqlQueryException
     */
    public function next()
    {
        $jobData = $this->shift();
        if (!empty($jobData)) {
            $this->setStatus($jobData['id'], self::STATUS_WORK);
            try {
                $this->run($jobData['filename'], $jobData['type']);
                $this->setStatus($jobData['id'], self::STATUS_COMPLETE);
                //unlink($jobData['filename']);
                return true;
            } catch (Exception $e) {
                $message = $e->getMessage() . PHP_EOL . $e->getTraceAsString();
                $this->setStatus($jobData['id'], self::STATUS_ERROR, $message);
            }
        }

        return false;
    }
}
