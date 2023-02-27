<?php

namespace Instrum\Main\Order;

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;
use Bitrix\Sale\BasketItem;
use Instrum\Main\Catalog\Product;

class XLSXBuilder{

    const TARGET_PERSON_ID = 3;

    /** @var \PhpOffice\PhpSpreadsheet\Spreadsheet */
    protected $spreadsheet;

    /** @var \Bitrix\Sale\Order */
    protected $order;

    protected $orderProperties = [];

    protected static $markUps = [
        'CONTACT' => 'G14',

        'MOBILE_PHONE' => 'G16',
        'MANAGER_EMAIL' => 'G17',

        'USER' => 'C22',

        'BASKET_START' => 28,
        'BASKET_SUM' => 'I28',
        'BASKET_NDS' => 'I29',
        'BASKET_TOTAL_PRICE' => 'I30',
        'DELIVERY_PLACE' => 'D34'
    ];

    protected static $contactData = ['CONTACT','MOBILE_PHONE','MANAGER_EMAIL'];

    protected static $basketMarkParts = [
        'NUMBER' => 'A',
        'ARTICLE' => 'B',
        'NAME' => 'C',
        'MEASURE' => 'F',
        'QUANTITY' => 'G',
        'PRICE' => 'H',
        'TOTAL_PRICE' => 'I'
    ];

    protected static $basketMerge = ["C:E"];

    protected static $userFields = [
        "NAME" => "", 
        "LAST_NAME" => "", 
        "UF_INN" => "ИНН #VALUE#", 
        "UF_KPP" => "КПП #VALUE#",
        "UF_LOCATION" => "г. #VALUE#",
        "UF_ADDRESS" => "",
        "PERSONAL_PHONE" => "тел.: #VALUE#"
    ];

    protected static $userProvide = [
        "UF_INN" => "INN",
        "UF_KPP" => "KPP",
        "UF_LOCATION" => "LOCATION",
        "UF_ADDRESS" => "STREET"
    ];

    protected static $managerProvide = [
        "CONTACT" => "TITLE",
        "MOBILE_PHONE" => "PERSONAL_PHONE",
        "MANAGER_EMAIL" => "EMAIL"
    ];

    public function __construct($orderId, $in = __DIR__.'/../../source/kp_sample.xlsx')
    {
        static::prepare();
        $this->order = \Bitrix\Sale\Order::load($orderId);
        $this->spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($in);

        $this->prepareOrderProperties();
    }

    private static function prepare()
    {
        Loader::includeModule("sale");
        Loader::includeModule("acrit.core");
        \Acrit\Core\Helper::includePhpSpreadSheet();
    }

    private final function prepareOrderProperties()
    {
        $arProperties = [];
        /** @var \Bitrix\Sale\PropertyValue $property */
        foreach($this->order->getPropertyCollection() as $property)
        {
            $arProperties[$property->getField("CODE")] = $property->getValue();
        }

        $this->orderProperties = $arProperties;
    }

    protected function getCellindex($code)
    {
        return static::$markUps[$code];
    }

    public function getRowIndex($code)
    {
        $cellId = static::$markUps[$code];
        if(preg_match("/\d{2}/", $cellId, $match))
        {
            return $match[0];
        }
        return 0;
    }

    protected final function drawCell($index, $value)
    {
        $this->spreadsheet->getActiveSheet()->setCellValue($index, $value);
    }

    public function modify()
    {
        $this->drawContacts();
        $this->drawUser();
        $this->drawDelivery();
        $this->drawBasketResult();
        $this->drawBasketItems();
        //
    }

    protected function drawContacts()
    {
        $manager = \CUser::GetByID($this->order->getField("CREATED_BY"))->Fetch();
        foreach($this->orderProperties as $propName => $propValue)
        {
            if(!in_array($propName, static::$contactData)) continue;
            if(!$propValue && $userKey = static::$managerProvide[$propName])
            {
                $propValue = $manager[$userKey];
            }
            $this->drawCell($this->getCellindex($propName), $propValue);
        }
    }

    public static function getCityByCode($code, $lang = 'ru')
    {
        \CModule::IncludeModule("sale");
        return \Bitrix\Sale\Location\LocationTable::getList([
            'filter' => [
                'CODE' => $code,
                'NAME.LANGUAGE_ID' => $lang,
            ],
            'select' => [
                'ID',
                'CODE',
                'LOCATION_NAME' => 'NAME.NAME'
            ]
        ])->fetch();
    }

    protected function drawUser()
    {
        if(
            ($arUserId = $this->order->getUserId()) &&
            ($user = \CUser::GetByID($arUserId)->Fetch())
        )
        {
            $fieldValues = [];
            foreach(static::$userFields as $code => $mask)
            {
                //if(!$user[$code]) continue;
                
                $value = $user[$code];
                if(!$value && static::$userProvide[$code])
                {
                    $value = $this->orderProperties[static::$userProvide[$code]];
                }

                if($code == "UF_LOCATION")
                {
                    $value = $this->getCityByCode($value)['LOCATION_NAME'];
                }

                if($value && $mask)
                {
                    $value = str_replace("#VALUE#", $value, $mask);
                }
                
                if($value) $fieldValues[] = $value;
            }
            $this->drawCell($this->getCellindex("USER"), implode(", ", $fieldValues));
        }
    }

    protected function drawDelivery()
    {

        $data = [];

        $shipment = $this->order->getShipmentCollection()->current();
        $deliveryId = $shipment->getField("DELIVERY_ID");
        $service = \Bitrix\Sale\Delivery\Services\Manager::getById($deliveryId);

        $data[] = $service["NAME"];

        if($this->orderProperties['STREET'])
        {
            $data[] = $this->orderProperties['STREET'];
        }

        $this->drawCell($this->getCellindex("DELIVERY_PLACE"), implode(", ", $data));
        $this->spreadsheet->getActiveSheet()->getRowDimension($this->getRowIndex("DELIVERY_PLACE"))->setRowHeight(-1);
    }

    protected function drawBasketResult()
    {
        $basket = $this->order->getBasket();
        $price = $basket->getPrice();

        $this->drawCell($this->getCellindex("BASKET_SUM"), $price);
        $this->drawCell($this->getCellindex("BASKET_TOTAL_PRICE"), $price);
        $this->drawCell($this->getCellindex("BASKET_NDS"), $price * .2);
    }

    protected function drawBasketItemLine($data)
    {
        $line = static::$markUps['BASKET_START'];
        $this->spreadsheet->getActiveSheet()->insertNewRowBefore($line, 1);

        foreach(static::$basketMerge as $cells)
        {
            list($start, $end) = explode(":", $cells);
            $this->spreadsheet->getActiveSheet()->mergeCells($start.$line.":".$end.$line);
        }

        foreach($data as $cellCode => $value)
        {
            if($cellColumn = static::$basketMarkParts[$cellCode])
            {
                $this->drawCell($cellColumn.$line, $value);
            }
        }
    }

    protected function drawBasketItems()
    {

        $items = [];

        /** @var BasketItem $item */
        foreach($this->order->getBasket()->getBasketItems() as $index => $item)
        {
            $product = (new Product())->getItem($item->getProductId());
            $line = [
                'NUMBER' => $index + 1,
                'ARTICLE' => $product->getProperty("CML2_ARTICLE")->getValue(),
                'NAME' => $product->getField("NAME"),
                'MEASURE' => $item->getField("MEASURE_NAME"),
                'QUANTITY' => $item->getQuantity(),
                'PRICE' => $item->getPrice(),
                'TOTAL_PRICE' => $item->getPrice() * $item->getQuantity()
            ];

            $items[] = $line;
        }

        foreach(array_reverse($items) as $line)
        {
            $this->drawBasketItemLine($line);
        }
    }

    private function get_file_id($excel_output)
    {
        return \CFile::SaveFile([
            'name' => 'KP_'.$this->order->getId().".xlsx",
            'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'MODULE_ID' => 'local.main',
            'content' => $excel_output
        ], 'order_kp');
    }

    public function save($out = __DIR__.'/../../source/kp_sample_output.xlsx')
    {
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($this->spreadsheet, "Xlsx");
        ob_start();
        $writer->save('php://output');
        $excel_output = ob_get_clean();

        return $this->get_file_id($excel_output);
    }

    public function updateProperty($file_id)
    {
        if($propValue = \Bitrix\Sale\PropertyValueCollection::getList([
            'select' => ['*'],
            'filter' => [
                '=ORDER_ID' => $this->order->getId(),
                '=CODE' => 'FILE_KP',
            ]
        ])->fetch())
        {
            \CFile::Delete($propValue['VALUE']);

            \CSaleOrderPropsValue::Update($propValue['ID'], [
                'VALUE' => $file_id
            ]);
        }
        else
        {
            $prop = \CSaleOrderProps::GetList([] , ["CODE" => "FILE_KP", "PERSON_TYPE_ID" => $this->order->getField("PERSON_TYPE_ID")])->Fetch();
            \CSaleOrderPropsValue::Add([
                'ORDER_ID' => $this->order->getId(),
                'ORDER_PROPS_ID' => $prop['ID'],
                'NAME' => $prop['NAME'],
                'CODE' => 'FILE_KP',
                'VALUE' => $file_id,
            ]);
        }

    }

    public static function installEvent()
    {
        EventManager::getInstance()->registerEventHandler("sale","OnSaleOrderSaved", 'local.main', "\\Instrum\\Main\\Order\\XLSXBuilder", "onOrderSave");
    }

    public static function onOrderSave($event)
    {
        /** @var \Bitrix\Sale\Order $order */
        $order = $event->getParameter("ENTITY");

        if($order->getPersonTypeId() == static::TARGET_PERSON_ID && !$order->isCanceled())
        {
            $builder = new \Instrum\Main\Order\XLSXBuilder($order->getId());
            $builder->modify();
            $file_id = $builder->save();
            $builder->updateProperty($file_id);
        }
    }

}