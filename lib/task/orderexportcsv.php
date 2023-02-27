<?php

namespace Instrum\Main\Task;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Internals\StatusLangTable;
use Bitrix\Sale\PropertyValueCollection;

class OrderExportCsv
{
    const EXPORT_DIR = '/out/csv/';
    const EXPORT_FILE = 'orders.csv';
    const CALL_ORDER_USER_GROUPS = [
        1, //Администраторы
        6, //Оператор КЦ
    ];
    const FILE_HEADERS = [
        'Дата оформления',
        'Интернет магазин',
        'ID заказа',
        'ID товара',
        'Количество товара',
        'Сумма',
        'Оформление',
        'Статус',
        'Регион'
    ];
    public $exportFile = null;

    static public $params = [];

    public function __construct()
    {
        static::prepare();
        $this->checkExportDir();
        $this->exportFile = $_SERVER['DOCUMENT_ROOT'] . self::EXPORT_DIR . self::EXPORT_FILE;

    }

    private static function prepare()
    {
        //Loader::includeModule('iblock');
        //Loader::includeModule('catalog');
        Loader::includeModule('sale');
    }

    public function export()
    {
        $result = ['ordersCount' => 0];

        $filterDateTime = DateTime::createFromTimestamp(strtotime('-90 days'));
        //$filterDateTime = \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime('-1 days'));

        self::$params['orderStatuses'] = $this->getOrderStatuses();

        $dbRes = Order::getList([
            'filter' => [
                //'USER_ID' => $USER->GetID(),
                '>DATE_INSERT' => $filterDateTime,
            ],
            'order' => ['ID' => 'DESC']
        ]);
        //$count = $dbRes->getSelectedRowsCount();
        $i = 0;
        $data = [];
        while ($order = $dbRes->fetch())
        {
            $i++;
            echo 'order handling ID - ' . $order['ID'], PHP_EOL;

            $dbPropRes = PropertyValueCollection::getList([
                'select' => ['*'],
                'filter' => [
                    'LOGIC' => 'OR',
                    [
                        '=ORDER_ID' => $order['ID'],
                        'CODE' => 'REGION',
                    ],
                    [
                        '=ORDER_ID' => $order['ID'],
                        'CODE' => 'UTM_LAST',
                    ],
                    [
                        '=ORDER_ID' => $order['ID'],
                        'CODE' => 'UTM_FIRST',
                    ],
                ]
            ]);

            $regionValue = '';
            $isYamarketFirst = false;
            $isYamarketLast = false;

            while ($itemProp = $dbPropRes->fetch()) {
                if (!empty($itemProp['VALUE'])) {
                    switch ($itemProp['CODE']) {
                        case 'REGION':
                            $regionValue = iconv('utf-8', 'windows-1251', $itemProp['VALUE']);
                            break;
                        case 'UTM_LAST':
                            $isYamarketLast = $this->checkUtmIsYandexMarket($itemProp['VALUE']);
                            break;
                        case 'UTM_FIRST':
                            $isYamarketFirst = $this->checkUtmIsYandexMarket($itemProp['VALUE']);
                            break;
                    }
                }
            }

            if (!($isYamarketFirst || $isYamarketLast)) {
                continue;
            }

            $basketRes = Basket::getList([
                'select' => ['NAME', 'QUANTITY', 'PRODUCT_ID', 'PRICE'],
                'filter' => [
                    '=ORDER_ID' => $order['ID'],
                ]
            ]);

            while ($item = $basketRes->fetch())
            {
                $dataItem = [];
                $dataItem[] = $order['DATE_INSERT']->toString();
                $dataItem[] = 'mysite.ru';
                $dataItem[] = $order['ID'];
                $dataItem[] = $item['PRODUCT_ID'];
                $dataItem[] = (int)$item['QUANTITY'];
                $dataItem[] = $item['PRICE']*$item['QUANTITY'];
                
                $oformlenieStr = $this->getOformlenieFieldValue($order['USER_ID']);                
                $dataItem[] = iconv("utf-8", "windows-1251", $oformlenieStr);
                
                if (self::$params['orderStatuses'][$order['STATUS_ID']]) {
                    $statusStr = self::$params['orderStatuses'][$order['STATUS_ID']]['NAME'];
                    $dataItem[] = iconv("utf-8", "windows-1251", $statusStr);
                }    
                else
                    $dataItem[] = '';
                $dataItem[] = $regionValue;
                $data[] = $dataItem;
            }
            $result['ordersCount']++;
        }

        $fp = fopen($this->exportFile, 'w');
        
        
        $headers = [];
        foreach (self::FILE_HEADERS as $item) {
            $headers[] = iconv("utf-8", "windows-1251", $item);
        }
        
        fputcsv($fp, $headers, ';');

        foreach ($data as $fields) {
            fputcsv($fp, $fields, ';');
        }
        fclose($fp);
        return $result;
    }

    /**
     * @param mix $value
     * @return bool
     */
    protected function checkUtmIsYandexMarket($value)
    {
        if(empty($value)) {
            return false;
        }
        if(is_array($value)) {
            $value = implode(' ', $value);
        }

        if (is_string($value)) {
            return strpos($value, 'market') !== false && strpos($value, 'regmarkets') === false;
        }

        return false;
    }

    /**
     * если заказ создан юзером из групп Кц/админ - то значение Телефон, иначе Сайт
     * @param $userId
     * @return string
     */
    protected function getOformlenieFieldValue($userId)
    {
        if (isset(self::$params['user_groups'][$userId])) {
            $userGroups = self::$params['user_groups'][$userId];
        } else {
            $userGroups = \CUser::GetUserGroup($userId);
            self::$params['user_groups'][$userId] = $userGroups;
        }

        $intersect = array_intersect ($userGroups, self::CALL_ORDER_USER_GROUPS);
        if (count($intersect)) {
            return 'Телефон';
        } else {
            return 'Сайт';
        }

    }

    protected function getOrderStatuses()
    {
        $result = [];
        $statusResult = StatusLangTable::getList(array(
            'order' => ['STATUS.SORT' => 'ASC'],
            'filter' => [
                'STATUS.TYPE'=>'O',
                'LID' => 'ru'
            ],
            'select' => ['STATUS_ID','NAME','DESCRIPTION']
        ));

        while ($status = $statusResult->fetch()) {
            $result[$status['STATUS_ID']] = $status;
        }
        return $result;
    }

    protected function checkExportDir()
    {
        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . self::EXPORT_DIR))
            mkdir($_SERVER['DOCUMENT_ROOT'] . self::EXPORT_DIR);
    }

}
