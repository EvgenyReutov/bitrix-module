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
    if (isset($_REQUEST["URL_DATA_FILE_PRODUCTS"]) && !empty($_REQUEST["URL_DATA_FILE_PRODUCTS"])) {
        $path = $_REQUEST["URL_DATA_FILE_PRODUCTS"];

        $filename = $_SERVER["DOCUMENT_ROOT"] . $path;
        $handle = fopen($filename, "r");

        if ($handle !== false) {
            while (($data = fgetcsv($handle, 3000, ";")) !== false) {
                $data = array_filter($data);
                $data = array_map(function($value) {
                    return iconv("windows-1251", "UTF-8", $value);
                }, $data);
                $arArtnumbers = array_merge($arArtnumbers, $data);
            }
            fclose($handle);
        }
    }

    if(empty($arArtnumbers)){
        echo json_encode(["error" => 'на заданы артикулы']);
        die();
    }

    // получаем товары по артикулам

    $rs = CIBlockElement::GetList([],["IBLOCK_ID" => CATALOG_IB, "PROPERTY_CML2_ARTICLE" => $arArtnumbers],false,false,["ID", "DETAIL_PAGE_URL"]);
    $arUrls = [];
    while($result = $rs->GetNext()){
        $arUrls[] = $result["DETAIL_PAGE_URL"];
    }

    if(empty($arUrls)){
        echo json_encode(["error" => 'не удалось определить товары по указанным артикулам']);
        die();
    }
    $arUrls = array_values($arUrls);
    $result["urls"] = $arUrls;
    echo json_encode($result);
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