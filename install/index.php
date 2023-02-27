<?php
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\ModuleManager;

Loc::loadMessages(__FILE__);


class local.main extends CModule{
	
	function __construct(){
		$arModuleVersion = array();
		include(__DIR__."/version.php");

		$this->MODULE_ID = "local.main";
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = Loc::getMessage("MAIN_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("MAIN_MODULE_DESCRIPTION");

		$this->PARTNER_NAME = Loc::getMessage("MAIN_PARTNER_NAME");
		$this->PARTNER_URI = Loc::getMessage("MAIN_PARTNER_URI");
	}

	function DoInstall(){
		if($this->isVersionD7()){
			ModuleManager::registerModule($this->MODULE_ID);            
		}
	}
	function DoUninstall(){		
		ModuleManager::unRegisterModule($this->MODULE_ID);
	}
	function isVersionD7(){
		return CheckVersion(ModuleManager::getVersion('main'), "14.0.0");
	}
}