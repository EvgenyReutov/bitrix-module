<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_after.php");
require 'CModuleOptions.php';

IncludeModuleLangFile(__FILE__);


$module_id = 'local.main';
$showRightsTab = false;
$arTabs = array(
    ['DIV' => 'delivery','TAB' => 'Настройка доставки','ICON' => '','TITLE' => ''],
    ['DIV' => 'events','TAB' => 'Настройка событий и Агентов','ICON' => '','TITLE' => ''],
    ['DIV' => 'lk','TAB' => 'Личный кабинет','ICON' => '','TITLE' => ''],
);
$arGroups = [
    'MAIN' => array('TITLE' => 'Перерасчет доставки', 'TAB' => 1),
    'CACHE_AGENT' => array('TITLE' => 'Агенты управления кэшем', 'TAB' => 2),
    'LK' => array('TITLE' => 'Настройка личного кабинета', 'TAB' => 3)
];
$arOptions = array(
    'use_delivery_calc' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Включить перерасчет',
        'TYPE' => 'CHECKBOX',
        'SORT' => '10',
    ),
    'delivery_start_date' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Начальная дата расчета',
        'TYPE' => 'DATE',
        'SORT' => '20',
    ),
    'target_start_date' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Целевая дата расчета',
        'TYPE' => 'DATE',
        'SORT' => '30',
    ),

    'refresh_cache_from_discounts' => array(
        'GROUP' => 'CACHE_AGENT',
        'TITLE' => 'Обновлять кэш при деактивации скидок',
        'TYPE' => 'CHECKBOX',
        'SORT' => '10',
    ),

    'cache_iblocks' => array(
        'GROUP' => 'CACHE_AGENT',
        'TITLE' => 'Инфоблоки',
        'TYPE' => 'MSELECT',
        'SORT' => '20',
        'VALUES' => [
            'REFERENCE_ID' => [
                10
            ],
            'REFERENCE' => [
                'Каталог'
            ]
        ],
    ),

    'refresh_cache_from_discounts_force' => array(
        'GROUP' => 'CACHE_AGENT',
        'TITLE' => 'Полный сброс',
        'TYPE' => 'CHECKBOX',
        'SORT' => '30',
        'DEFAULT' => 0,
        'NOTES' => 'Очищает кэш всего сайта'
    ),

    'lk_use_linked_items' => array(
        'GROUP' => 'LK',
        'TITLE' => 'Показывать связанные товары',
        'TYPE' => 'CHECKBOX',
        'SORT' => '10',
        'DEFAULT' => 'N',
    ),
);


/*
Конструктор класса CModuleOptions
$module_id - ID модуля
$arTabs - массив вкладок с параметрами
$arGroups - массив групп параметров
$arOptions - собственно сам массив, содержащий параметры
$showRightsTab - определяет надо ли показывать вкладку с настройками прав доступа к модулю ( true / false )
*/

$opt = new CModuleOptions($module_id, $arTabs, $arGroups, $arOptions, $showRightsTab);
$opt->ShowHTML();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_admin.php");?>

