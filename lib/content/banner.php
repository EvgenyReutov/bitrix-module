<?
namespace Instrum\Main\Content;

class Banner{

	public function __construct(){ 
		$GLOBALS["APPLICATION"]->AddHeadScript("/local/modules/local.main/js/cloudflare.min.js");
		$GLOBALS["APPLICATION"]->AddHeadScript("/local/modules/local.main/js/banner_list_popup_select.js");
	}

	public function createArtBtn(&$item){
        $item[] = $this->getParams([
        	'title' => 'ограничение по артикулам',
        	'content_url' => "/local/modules/local.main/admin/banner_list_popup_select.php",
            'content_post' => "ID=".$_REQUEST["ID"],
        ], "banner_items_save");
	}
	public function createSectionBtn(&$item){		
        $item[] = $this->getParams([
        	'title' => 'ограничение по разделам',
        	'content_url' => "/local/modules/local.main/admin/banner_section_popup_select.php",
            'content_post' => "ID=".$_REQUEST["ID"],
        ], "banner_section_save");
	}

	private function getParams($arParams, $saveBtnID){
		$arDialogParams = array(
            'title' => 'выбрать списком',
            'content_url' => "/local/modules/local.main/admin/banner_list_popup_select.php",
            'content_post' => "ID=".$_REQUEST["ID"],
            'width' => 900,
            'height' => 600,
            'buttons' => array(
                array(
                    "title" => "сохранить",
                    "name" => "save",
                    "id" => $saveBtnID,
                    "className" => 'adm-btn-save',
                ),
                '[code]BX.CDialog.prototype.btnClose[code]'
            ),
            'saveBtn' => $saveBtnID
        );

        $arDialogParams = array_merge($arDialogParams, $arParams);

        $strParams = \CUtil::PhpToJsObject($arDialogParams);
        $strParams = str_replace('\'[code]', '', $strParams);
        $strParams = str_replace('[code]\'', '', $strParams);

        return [
            "TEXT"=>$arDialogParams['title'],
            "ICON"=>"",
            "TITLE"=>$arDialogParams['title'],
            "LINK" => 'javascript:(
                new JSArtSelect('.$strParams.')
            )',
        ];
	}
}