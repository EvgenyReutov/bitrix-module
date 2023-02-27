<?php
namespace Instrum\Main\Import;

class MergeImage{

    protected $iblock_from;
    protected $iblock_to;

    protected $fields = [
        "PREVIEW_PICTURE",
        "DETAIL_PICTURE",
        "PROPERTY_MORE_PHOTO"
    ];

    protected $link_property = 'CML2_ARTICLE';

    protected $db;

    public function __construct($from, $to)
    {
        $this->iblock_from = $from;
        $this->iblock_to = $to;

        global $DB;
        $this->db = $DB;
    }

    protected function createTable()
    {
        $this->db->Query('CREATE TABLE IF NOT EXISTS itorg_merge_images(
            FROM_ID INT, 
            LINK_DATA VARCHAR(255), 
            TO_ID INT);');
        $this->db->Query('TRUNCATE TABLE itorg_merge_images');
    }

    protected function pushData($fromId, $linked)
    {
        $this->db->Query('INSERT INTO itorg_merge_images SET 
            FROM_ID = '.$this->db->ForSql($fromId).',
            LINK_DATA = "'.$this->db->ForSql($linked).'"');
    }

    protected function updateData($toId, $linked)
    {
        $this->db->Query('UPDATE itorg_merge_images 
            SET TO_ID = '.$this->db->ForSql($toId).'
            WHERE LINK_DATA = "'.$this->db->ForSql($linked).'"');
    }

    protected function cutTable()
    {
        $this->db->Query('DELETE FROM itorg_merge_images WHERE TO_ID IS NULL');
    }

    protected function releaseTable($fromId)
    {
        $this->db->Query('DELETE FROM itorg_merge_images WHERE FROM_ID = '.$this->db->ForSql($fromId));
    }

    protected function dropTable()
    {
        $this->db->Query('DROP TABLE itorg_merge_images');
    }

    public function iterator()
    {
        while(true)
        {
            $rows = $this->db->Query('SELECT * FROM itorg_merge_images LIMIT 20');
            if(!$rows->SelectedRowsCount())
            {
                break;
            }

            while($row = $rows->Fetch())
            {
                yield $row;
                $this->releaseTable($row['FROM_ID']);
            }
        }
    }

    public function merge($row)
    {
        if(
            ($fromElement = $this->getElement($row['FROM_ID'])) &&
            ($toElement = $this->getElement($row['TO_ID']))
        )
        {
            $arFields = [];
            $arProps = [];

            foreach($this->fields as $field)
            {
                if(strpos($field, "PROPERTY_") !== False)
                {
                    $propCode = str_replace("PROPERTY_", "", $field);
                    if(is_array($fromElement['PROPERTIES'][$propCode]['VALUE']))
                    {
                        $values = [];
                        foreach($fromElement['PROPERTIES'][$propCode]['VALUE'] as $value)
                        {
                            $values[] = CFile::MakeFileArray(CFile::GetFileArray($value)['SRC']);
                        }
                        $arProps[$propCode] = $values;
                    }
                    else if($value = $fromElement['PROPERTIES'][$propCode]['VALUE'])
                    {
                        $arProps[$propCode] = CFile::MakeFileArray(CFile::GetFileArray($value)['SRC']);
                    }
                }
                else if($value = $fromElement[$field])
                {
                    $arFields[$field] = CFile::MakeFileArray(CFile::GetFileArray($value)['SRC']);
                }
            }

            if($arFields) (new CIBlockElement())->Update($toElement['ID'], $arFields);
            if($arProps) CIBlockElement::SetPropertyValuesEx($toElement['ID'], $toElement['IBLOCK_ID'], $arProps);

        }
    }

    public function start()
    {
        $this->createTable();
        $this->fillFromData();
        $this->fillToData();

        $this->cutTable();

        foreach($this->iterator() as $row)
        {
            $this->merge($row);
        }
    }

    protected function getElement($id)
    {
        if($element = \CIBlockElement::GetList([], ['ID' => $id])->GetNextElement())
        {
            $arElement = $element->GetFields();
            $arElement['PROPERTIES'] = $element->GetProperties();

            return $arElement;
        }
    }

    protected function getFromFilter()
    {
        return [
            'IBLOCK_ID' => $this->iblock_from,
            '!PROPERTY_'.$this->link_property => False
        ];
    }

    protected function getToFilter()
    {
        return [
            'IBLOCK_ID' => $this->iblock_to,
            '!PROPERTY_'.$this->link_property => False
        ];
    }

    protected function fillFromData()
    {
        $it = \CIBlockElement::GetList([], $this->getFromFilter());

        while($element = $it->GetNextElement())
        {
            $arElement = $element->GetFields();
            $arElement['PROPERTIES'] = $element->GetProperties();

            $this->pushData($arElement['ID'], $arElement['PROPERTIES'][$this->link_property]['VALUE']);
        }
    }

    protected function fillToData()
    {
        $it = \CIBlockElement::GetList([], $this->getToFilter());

        while($element = $it->GetNextElement())
        {
            $arElement = $element->GetFields();
            $arElement['PROPERTIES'] = $element->GetProperties();

            $this->updateData($arElement['ID'], $arElement['PROPERTIES'][$this->link_property]['VALUE']);
        }
    }

}

(new MergeImage(10, 20))->start();