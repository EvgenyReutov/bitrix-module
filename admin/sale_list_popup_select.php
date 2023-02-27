<?
use Bitrix\Main\Loader;

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule('iblock');
Loader::includeModule('catalog');
Loader::includeModule('sale');

if(isset($_REQUEST["save"])){
    Header('Content-type: application/json');
    $arResult["status"] = false;

    $arArtnumbers = [];

    // парсим свойство строковых артикулов
    if (isset($_REQUEST["artNumberList"]) && !empty($_REQUEST["artNumberList"])) {
        $arArtnumbers = array_merge($arArtnumbers, explode(",", $_REQUEST["artNumberList"]));
    }
    $isMassFile = false;
    if (isset($_REQUEST["URL_DATA_FILE_PRODUCTS"]) && !empty($_REQUEST["URL_DATA_FILE_PRODUCTS"])) {
        $path = $_REQUEST["URL_DATA_FILE_PRODUCTS"];

        $filename = $_SERVER["DOCUMENT_ROOT"] . $path;
        $handle = fopen($filename, "r");

        $arTree = [];
        if ($handle !== false) {
            while (($data = fgetcsv($handle, 3000, ";")) !== false) {
                $data = array_filter($data);
                $data = array_map(function($value) {
                    return iconv("windows-1251", "UTF-8", $value);
                }, $data);
                if ((int)$data[1] && is_array($data)) {
                    $isMassFile = true;
                    $arTree[$data[1]][] = $data[0];
                    $arArtList[] = $data[0];
                } else {
                    $arArtnumbers = array_merge($arArtnumbers, $data);
                }
            }
            fclose($handle);
        }
    }
    // получаем товары по артикулам

    if ($isMassFile) {
        foreach ($arTree as $discount => $artList) {
            $arTree[$discount] = [];
            $rs = CIBlockElement::GetList(
                [],
                [
                    "IBLOCK_ID" => CATALOG_IB,
                    "PROPERTY_CML2_ARTICLE" => $artList
                ],
                false,
                false,
                ["ID"]
            );
            while ($result = $rs->Fetch()) {
                $arTree[$discount][] = $result["ID"];
            }
        }
    } else {
        $rs = CIBlockElement::GetList(
            [],
            [
                "IBLOCK_ID" => CATALOG_IB,
                "PROPERTY_CML2_ARTICLE" => $arArtnumbers
            ],
            false,
            false,
            ["ID"]
        );
        $items = [];
        while ($result = $rs->Fetch()) {
            $items[] = $result["ID"];
        }
    }

    $rsProductDiscounts = \CSaleDiscount::GetList(
        array("SORT" => "ASC"),
        array("ID" => 1),
//        array("ID" => $_REQUEST["ID"]),
        false,
        false,
        array("ACTIVE_FROM", "ACTIVE_TO", "ID", "VALUE", "XML_ID", "CONDITIONS", "ACTIONS")
    )->Fetch();

    $action = unserialize($rsProductDiscounts["ACTIONS"]);
    $action["CHILDREN"][0]["DATA"]["All"] = "OR";

    if ($isMassFile) {
        $upd = [
            'ACTIONS' => [
                'CLASS_ID' => 'CondGroup',
                'DATA' => ['All' => 'AND'],
                'CHILDREN' => []
            ]
        ];
        foreach ($arTree as $discount => $prods) {
            $upd['ACTIONS']['CHILDREN'][] = [
                'CLASS_ID' => 'ActSaleBsktGrp',
                'DATA' => [
                    'Type' => 'Discount',
                    'Value' => $discount,
                    'Unit' => 'Perc',
                    'Max' => 0,
                    'All' => 'OR',
                    'True' => 'True'
                ],
                'CHILDREN' => [
                    [
                        'CLASS_ID' => 'CondIBElement',
                        'DATA' => [
                            'logic' => 'Equal',
                            'value' => $prods
                        ]
                    ]
                ]
            ];
        }
        if (\CSaleDiscount::Update(
            $_REQUEST["ID"],
            array(
                "ACTIONS" => serialize($upd['ACTIONS']),
            )
        )) {
            $arResult["status"] = true;
        }
    } else {
        $children = [
            "CLASS_ID" => "CondIBElement",
            "DATA" => [
                "logic" => "Equal",
                "value" => []
            ]
        ];


        $arValues = [];
        foreach ($items as $prod) {
            $arValues[] = $prod;
        }

        $children["DATA"]["value"] = $arValues;

        $action["CHILDREN"][0]["CHILDREN"][0] = $children;

        if (\CSaleDiscount::Update(
            $_REQUEST["ID"],
            array(
                "ACTIONS" => serialize($action),
            )
        )) {
            $arResult["status"] = true;
        }
    }

    echo json_encode($arResult);
    die();
}
?>
<tr valign="top" class="select-file-form">
    <td colspan="2">
        <form action="<? POST_FORM_ACTION_URI ?>" name="form_import_element" id="form_import_element" method="post">
            <div class="mb_10">
                <div class="mb_10">Выберите файл для обработки</div>
                <input type="text" id="URL_DATA_FILE_PRODUCTS" name="URL_DATA_FILE_PRODUCTS" size="30"
                       value="<?= htmlspecialchars($URL_DATA_FILE_PRODUCTS) ?>">
                <input type="button" value="Открыть" onclick="getXlsFileProducts()" class="btn-file__load">
                <? CAdminFileDialog::ShowScript(
                    Array(
                        "event" => "getXlsFileProducts",
                        "arResultDest" => array(
                            "FORM_NAME" => "form_import_element",
                            "FORM_ELEMENT_NAME" => "URL_DATA_FILE_PRODUCTS"
                        ),
                        "arPath" => array(
                            "SITE" => SITE_ID,
                            "PATH" => "/upload/"
                        ),
                        "select" => 'F',
                        "operation" => 'O',
                        "showUploadTab" => true,
                        "showAddToMenuTab" => false,
                        "fileFilter" => 'xls,csv,xlsx',
                        "allowAllFiles" => true,
                        "SaveConfig" => true,
                    )
                ); ?>
            </div>
            <div class="mb_10">
                <div class="mb_10">Укажите артикулы через запятую.</div>
                <textarea name="artNumberList" cols="41" rows="10z"></textarea>
            </div>
        </form>
    </td>
</tr>