<?php


namespace Instrum\Main\Exchange;

use Instrum\Main\Exchange\Dto;
use \RuntimeException;
use \SimpleXMLElement;

class Reader
{
    /** @var SimpleXMLElement */
    protected $xml;

    /**
     * Reader constructor.
     * @param string $filename
     */
    public function __construct($filename)
    {
        if(!is_readable($filename)) {
            throw new RuntimeException('File ' . $filename . ' is not readable');
        }

        $this->xml = new SimpleXMLElement(file_get_contents($filename));
    }

    public function readCategories()
    {
        foreach ($this->xml->Разделы->Раздел as $xmlCategory) {
            $parentUuid = (string) $xmlCategory->Родитель;
            $category = new Dto\Category((string) $xmlCategory->GUID, empty($parentUuid) ? null : $parentUuid, (string) $xmlCategory->Наименование);
            yield $category;
        }
    }

    public function readFeatures()
    {
        $featureTypeMap = [
            'Справочник' => Dto\Feature::TYPE_ENUM,
            'Число' => Dto\Feature::TYPE_NUMERIC,
            'Строка' => Dto\Feature::TYPE_STRING,
            'Булево' => Dto\Feature::TYPE_BOOLEAN
        ];

        foreach ($this->xml->ДополнительныеРеквизиты->ДополнительныйРеквизит as $xmlFeature) {
            $feature = new Dto\Feature(
                (string) $xmlFeature->GUID,
                $featureTypeMap[(string) $xmlFeature->ТипЗначения],
                (string) $xmlFeature->Наименование
            );

            if($feature->type == Dto\Feature::TYPE_ENUM) {
                foreach ($xmlFeature->Значения->Значение as $xmlFeatureValue) {
                    $feature->enum[] = new Dto\FeatureValue(
                        (string) $xmlFeatureValue->GUID,
                        (string) $xmlFeatureValue->Наименование
                    );
                }
            }

            yield $feature;
        }
    }

    public function readProducts()
    {
        foreach ($this->xml->Товары->Товар as $xmlProduct) {
            try {
                $product = new Dto\Product(
                    (string) $xmlProduct->GUID,
                    (string) $xmlProduct->Наименование,
                    (string) $xmlProduct->Штрихкод,
                    (string) $xmlProduct->Артикул,
                    ceil(floatval($xmlProduct->Длина) * 1000),
                    ceil(floatval($xmlProduct->Ширина) * 1000),
                    ceil(floatval($xmlProduct->Высота) * 1000),
                    ceil(floatval($xmlProduct->Вес) * 1000),
                    (string) $xmlProduct->Описание
                );

                $product->name = str_replace('&nbsp;', ' ', $product->name);
                $product->name = str_replace('&nbsp;', ' ', htmlentities($product->name));
                $product->name = html_entity_decode($product->name);

                foreach ($xmlProduct->Разделы->Раздел as $xmlProductCategory) {
                    $product->categories[] = (string) $xmlProductCategory->attributes()->GUID;
                }

                foreach ($xmlProduct->ДополнительныеРеквизиты->ДополнительныйРеквизит as $xmlFeatureValue) {
                    $product->featureValues[] = [
                        'uuid' => (string) $xmlFeatureValue->GUID,
                        'value' => (string) $xmlFeatureValue->Значение,
                    ];
                }

                yield $product;
            } catch (\Exception $e) {

            }
        }
    }

    public function readRemoveUnaffected()
    {
        return $this->xml->ДеактивироватьНепопавшиеВОбмен == 'true';
    }
}