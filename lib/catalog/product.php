<?

namespace Instrum\Main\Catalog;

use Instrum\Main\Iblock\Properties;

class Product
{
    public $IBLOCK_CODE = "catalog";
    public $data;

    public function __construct($data = false)
    {
        if($data) $this->data = $data;
    }

    public function getIblockId(){
        return \CIBlock::GetList(
            [],
            ["CODE" => $this->IBLOCK_CODE]
        )->Fetch()["ID"];
    }

    public function getItem($id){
        if(!$rs = \CIBlockElement::GetList(
            [],
            ["IBLOCK_ID" => $this->getIblockId(), "ID" => $id],
            false,false,
            []
        )->GetNextElement()){
            throw new \Exception("элемент не найден");
        }

        $data["FIELDS"] = $rs->GetFields();
        $data["PROPERTIES"] = $rs->GetProperties();

        return new Product($data);
    }

    public function getField($field){
        if(!isset($this->data["FIELDS"][$field])){
            throw new \Exception("неизвестное поле элемента [".$field."]");
        }
        return $this->data["FIELDS"][$field];
    }

    public function getProperty($key){
        if(!isset($this->data["PROPERTIES"][$key])){
            throw new \Exception("неизвестное свойство элемента [".$key."]");
        }
        return new Properties($this->data["PROPERTIES"][$key]);
    }

    public function getAvailableProps(){
        return array_keys($this->data["PROPERTIES"]);
    }

    public function getProtectedProperties()
    {
        return [
            'MORE_PHOTO',
            'FILES',
            'BREND',
            'MARK',
            'ATTACHMENTS',
            'KLASSIFIKATOR'
        ];
    }

    public function isProtectedProperty($arProperty)
    {
        return (
            strstr($arProperty['CODE'], "CML2_") ||
            in_array($arProperty['CODE'], $this->getProtectedProperties()) ||
            strstr($arProperty["NAME"], "_")
        );
    }
}