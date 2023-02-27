<?php


namespace Instrum\Main\Attachment;


use Generator;

interface ReaderInterface
{
    /**
     * @return Generator
     * Return array of [sku => sku, filename => filename]
     */
    public function read();

    /**
     * @param array $data
     */
    public function markRead($data);
}