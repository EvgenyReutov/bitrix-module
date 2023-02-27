<?php

namespace Instrum\Main\Tools;

use Bitrix\Main\Loader;
use Bitrix\Seo\Engine\Bitrix;
use Instrum\Ajax\Controller;

class City{
    
    protected $id;
    protected $code;
    protected $displayName;
    
    protected function __construct($id, $code, $displayName)
    {
        $this->id = $id;
        $this->code = $code;
        $this->displayName = $displayName;
    }

    public static function getByName($name)
    {
        $id = static::getIDByName($name);
        $code = static::getCodeByName($name);

        return new City($id, $code, $name);
    }

    public static function getIDByName($name){
		global $DB;
		return $DB->Query("
            select LOCATION_ID from `b_sale_loc_name`
            where NAME = '".$name."' and LANGUAGE_ID = 'ru'
        ")->Fetch()["LOCATION_ID"];
	}
	public static function getCodeByName($name){
		global $DB;
		return $DB->Query("
			select loc.CODE from b_sale_loc_name name
			inner join b_sale_location loc
			on name.LOCATION_ID = loc.ID
			where name.NAME = '".$name."' and name.LANGUAGE_ID = 'ru'            
        ")->Fetch()["CODE"];
	}

    /**
     * @return mixed
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}