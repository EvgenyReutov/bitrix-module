<?php


namespace Instrum\Main\Attachment;

use RuntimeException;
use CIBlockElement;
use CFile;

class Grabber
{
    const TYPE_MANUAL = 1;
    const TYPE_CERTIFICATE = 2;

    const TYPE_DESCRIPTIONS = [
        self::TYPE_MANUAL => 'Инструкция по эксплуатации',
        self::TYPE_CERTIFICATE => 'Сертификат'
    ];

    const ATTACHMENTS_PROPERTY_CODE = 'ATTACHMENTS';

    const IBLOCK_ID = 20;

    /** @var ReaderInterface */
    protected $reader;
    /** @var int */
    protected $type;

    /**
     * Grabber constructor.
     * @param ReaderInterface $reader
     * @param int $type
     */
    public function __construct($reader, $type)
    {
        if(!($reader instanceof ReaderInterface)) {
            throw new RuntimeException('Wrong reader provided');
        }
        if (empty($type) || !in_array($type, [self::TYPE_MANUAL, self::TYPE_CERTIFICATE])) {
            throw new RuntimeException('Wrong type provided');
        }

        $this->reader = $reader;
        $this->type = $type;
    }

    /**
     *
     */
    public function grab()
    {
        foreach ($this->reader->read() as $fileData) {
            $this->uploadFile($fileData['sku'], $fileData['filename']);
            $this->reader->markRead($fileData);
        }
    }

    /**
     * @param string $sku
     * @param string $filename
     */
    protected function uploadFile($sku, $filename)
    {
        $product = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::IBLOCK_ID,
                'PROPERTY_CML2_ARTICLE' => $sku
            ])
            ->GetNext();

        if($product) {
            $productFiles = [];

            $productFilesRowset = CIBlockElement::GetProperty($product['IBLOCK_ID'], $product['ID'], [], ['CODE' => self::ATTACHMENTS_PROPERTY_CODE]);
            while($row = $productFilesRowset->GetNext()) {
                $rowId = $row['PROPERTY_VALUE_ID'];

                $productFiles[$rowId] = [
                    'VALUE' => $row['VALUE'],
                    'DESCRIPTION' => $row['DESCRIPTION'],
                ];

                if($row['DESCRIPTION'] == self::TYPE_DESCRIPTIONS[$this->type]) {
                    $productFiles[$rowId]['del'] = 'Y';
                }
            }

            $uploaded = CFile::MakeFileArray($filename);
            if($uploaded) {
                $productFiles['n0'] = [
                    'VALUE' => $uploaded,
                    'DESCRIPTION' => self::TYPE_DESCRIPTIONS[$this->type],
                ];
            }
            CIBlockElement::SetPropertyValues($product['ID'], $product['IBLOCK_ID'], $productFiles, self::ATTACHMENTS_PROPERTY_CODE);
        }
    }
}