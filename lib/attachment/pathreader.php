<?php


namespace Instrum\Main\Attachment;


use RuntimeException;
use Generator;

class PathReader implements ReaderInterface
{
    /** @var string */
    protected $path;

    /**
     * PathReader constructor.
     * @param $path
     */
    public function __construct($path)
    {
        if(empty($path)) {
            throw new RuntimeException('Path cannot be empty');
        }
        if(substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }
        if(!is_dir($path)) {
            throw new RuntimeException('Path is not a directory');
        }

        $this->path = $path;
    }

    /**
     * @inheritDoc
     */
    public function read()
    {
        $fileNames = scandir($this->path);
        if(!empty($fileNames)) {
            $fileNames = array_diff($fileNames, ['.', '..']);
            foreach ($fileNames as $fileName) {
                $sku = pathinfo($fileName, PATHINFO_FILENAME);
                if(!empty($sku)) {
                    yield [
                        'sku' => $sku,
                        'filename' => $this->path . $fileName
                    ];
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function markRead($data)
    {
        if($data && $data['filename']) {
            unlink($data['filename']);
        }
    }
}