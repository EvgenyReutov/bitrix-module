<?php


namespace Instrum\Main\Exchange\Reader;


use RuntimeException;
use XMLReader;

abstract class AbstractXmlReader
{
    /** @var string */
    private $fileData;
    /** @var bool */
    private $isUri;

    /**
     * @inheritDoc
     */
    public function __construct($fileData, $isUri = false)
    {
        if($isUri) {
            if (!is_readable($fileData)) {
                throw new RuntimeException('Input file is not readable');
            }
        } elseif (empty($fileData)) {
            throw new RuntimeException('File data should not be empty');
        }

        $this->fileData = $fileData;
        $this->isUri = $isUri;
    }

    /**
     * @return XMLReader
     */
    protected function getFileReader()
    {
        $reader = new XMLReader();
        if($this->isUri) {
            if (!$reader->open($this->fileData)) {
                throw new RuntimeException('Cannot open ' . $this->fileData . ' for XML reading');
            }
        } elseif (!$reader->XML($this->fileData)) {
            throw new RuntimeException('Given contents is not a valid XML string');
        }
        return $reader;
    }
}