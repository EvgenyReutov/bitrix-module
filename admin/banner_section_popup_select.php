<?
use Bitrix\Main\Loader;

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");

Loader::includeModule('iblock');
Loader::includeModule('catalog');
Loader::includeModule('sale');

if(isset($_REQUEST["save"])){
    Header('Content-type: application/json');
    $arResult["status"] = false;

    if(empty($_REQUEST["CATEGORY"])){
        echo json_encode(["error" => 'не выбраны разделы']);
        die();
    }

    $arSelect = ["ID", "NAME", "LEFT_MARGIN", "RIGHT_MARGIN", "SECTION_PAGE_URL"];

    $rs = CIBlockSection::GetList([],["IBLOCK_ID" => CATALOG_IB, "ID" => array_keys($_REQUEST["CATEGORY"])],false,$arSelect);
    $arUrls = [];
    while($res = $rs->GetNext()){        
        $arUrls[] = $res["SECTION_PAGE_URL"];

        if(($res["RIGHT_MARGIN"] - $res["LEFT_MARGIN"]) > 1){
            $subRs = CIBlockSection::GetList([],["IBLOCK_ID" => CATALOG_IB, ">LEFT_MARGIN" => $res["LEFT_MARGIN"], "<RIGHT_MARGIN" => $res["RIGHT_MARGIN"]],false,$arSelect);
            while($resSub = $subRs->GetNext()){
                $arUrls[] = $resSub["SECTION_PAGE_URL"];
            }
        }
    }
    $arUrls = array_unique($arUrls);

    if(empty($arUrls)){
        echo json_encode(["error" => 'не удалось определить пути к указанным разделам']);
        die();
    }
    $arUrls = array_values($arUrls);
    $result["urls"] = $arUrls;

    if(isset($_REQUEST["NOT"])){
        $result["NOT"] = true;
    }

    // $result["dump"] = $_REQUEST;

    echo json_encode($result);
    die();
}

$tree = CIBlockSection::GetTreeList(
    $arFilter=Array('IBLOCK_ID' => CATALOG_IB),
    $arSelect=Array("ID", "IBLOCK_SECTION_ID", "DEPTH_LEVEL", "NAME")
);

$arTree = [];
while($section = $tree->GetNext()) {
    if(empty($section["IBLOCK_SECTION_ID"])){
        $section["IBLOCK_SECTION_ID"] = 0;
    }
    $arTree[] = $section;
}

function getTree($items, $parent){    
    $arItems = [];
    foreach($items as $item){        
        if($item["IBLOCK_SECTION_ID"] == $parent){
            $item["ITEMS"] = getTree($items, $item["ID"]);
            $arItems[] = $item;
        }
    }
    return $arItems;
}
$tree = getTree($arTree, 0);
?>
<style type="text/css">
    #tree .items_block{
        display: none;
    }
    #tree .active{
        display: block;
    }
</style>
<form action="<?__FILE__?>" method="post" id="form_import_element">
<tr>
    <td width="40%" valign="top" class="adm-detail-content-cell-l">Исключить разделы?</td>
    <td width="60%" class="adm-detail-content-cell-r">
        <input
           type="checkbox" 
           name="NOT"
           id="designed_checkbox_catalog_NOT"
           class="adm-designed-checkbox">
       <label class="adm-designed-checkbox-label"
           for="designed_checkbox_catalog_NOT"
           title=""></label>
    </td>
</tr>
<br>
<br>
<tr>
    <td width="40%" valign="top" class="adm-detail-content-cell-l">Выберите разделы:</td>
    <td width="60%" class="adm-detail-content-cell-r">
        <div id="tree">
            
                <?
                function renderTree($items, $selectedParent = false, $hideChild = false){
                    foreach($items as $item):?>
                        <table 
                            border="0"
                            data-id="table_<?= $item["IBLOCK_SECTION_ID"] ?>"
                            cellspacing="0"
                            cellpadding="0"
                            class="items_block <?if($item["DEPTH_LEVEL"] == 1):?>active<?endif;?>"                        
                            >
                            <tbody>
                                <tr data-group-id="<?= $item["ID"] ?>">
                                    <td width="20" valign="top" align="center">
                                        <img src="/bitrix/images/catalog/load/<?= !empty($item["ITEMS"]) ? 'plus' : 'minus' ?>.gif"
                                             class="expand-group" width="13" height="13"
                                             id="img_<?= $item["ID"] ?>">
                                    </td>
                                    <td id="node_<?= $item["NAME"] ?>" class="category_node">
                                        <input type="checkbox" <? if ($item["SELECTED"] == "Y") echo 'checked'; ?>
                                               name="CATEGORY[<?= $item["ID"] ?>]"
                                               id="designed_checkbox_catalog_<?= $item["ID"] ?>"
                                               class="adm-designed-checkbox">
                                        <label class="adm-designed-checkbox-label"
                                               for="designed_checkbox_catalog_<?= $item["ID"] ?>"
                                               title=""></label>
                                        <a href="javascript: void(0)">
                                            <span class="text"><span class="text"><b><?=$item["NAME"]?></b></span></span>
                                        </a>
                                        <?if(!empty($item["ITEMS"])) renderTree($item["ITEMS"], ($item["SELECTED"] !== "Y"), $item["HIDE_CHILDS"]);?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?endforeach;
                }
                renderTree($tree, true);
                ?>
        </div>
    </td>
</tr>
</form>