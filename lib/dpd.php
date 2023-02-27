<?
namespace Instrum\Main;

use Bitrix\Main\Loader;
use Instrum\Main\Table\Dpd\CityesTable;
use \Bitrix\Main\Application;

class Dpd{

	const MY_NUMBER  = "1001055174";
	const MY_KEY     = "C9199901FFA96B9A9B5EAC6B8E8CA0F827AF8B6A";

	const DPD_GEO_URL = "geography2?wsdl";
	const DPD_CALC_URL = "calculator2?wsdl";

	const CSV_FILE = "/test/dpd/cities.csv";

	public $arServer = [
		"real" => 'http://ws.dpd.ru/services/',
		"test" => 'http://wstest.dpd.ru/services/'
	];

	public $arWeights = [
	    1000
	];

	public $start;

	public $mode = 'test';
	public $sposob = 'pvz';
	public $service = 'DPD Online Classic';
	public $server;
	public $client;
	public $app;
	public $dbHelper;

	public function __construct(){
		$this->start = time();

		$this->app = Application::getConnection();
		$this->dbHelper = $this->app->getSqlHelper();
		$this->setMode("test");
	}

	public function getWasteTime(){
		return (time() - $this->start);
	}

	public function setMode($mode = 'test'){
		if(in_array($mode, ["real", "test"])){
			$this->mode = $mode;
			$this->server = $this->arServer[$mode];
		}
		return $this;
	}

	public function setGeoClient(){
		$this->client = new \SoapClient ($this->server.self::DPD_GEO_URL);
		return $this;
	}

	public function setCalcClient(){
		$this->client = new \SoapClient ($this->server.self::DPD_CALC_URL);
		return $this;
	}

	public function getAllCity($parse = true) {
		$this->app->query("TRUNCATE TABLE `".CityesTable::getTableName()."`");

	    $this->setGeoClient();
	    
	    $rsCities = $this->client->getCitiesCashPay([
	    	"request" => [
	    		"auth" => [
	    			'clientNumber' => self::MY_NUMBER,
	        		'clientKey' => self::MY_KEY
	    		]
	    	]
	    ]);

	    foreach($rsCities as $k => $city){
	    	foreach($city as $i => $c){
    			CityesTable::add([	    				
					"CITY_ID" => $c->cityId,
					"COUNTRY_CODE" => $c->countryCode,
					"COUNTRY_NAME" => $c->countryName,
					"REGION_CODE" => $c->regionCode,
					"REGION_NAME" => $c->regionName,
					"CITY_CODE" => $c->cityCode,
					"CITY_NAME" => $c->cityName,
					"ABBREVIATION" => $c->abbreviation,
					"INDEX_MIN" => $c->indexMin,
					"INDEX_MAX" => $c->indexMax
    			]);
    			fwrite(STDOUT, $i."\r");
	    	}
	    }
	}

	public function parseCsv(){

		if($this->app->query("SELECT * FROM `dpd_city_cost` WHERE `STATUS` = 0 LIMIT 1")->Fetch()){
			return $this;
		}

		$this->app->query("DROP TABLE `dpd_city_cost`");
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `dpd_city_cost`(
				`ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				`CITY_NAME` VARCHAR(100),
				`LAST_WEIGHT` INT(100),
				`COSTS` TEXT,
				`RESULT` VARCHAR(255),
				`PERIOD` VARCHAR(255),
				`STATUS` INT(2) DEFAULT 0,
				INDEX (`CITY_NAME`)
			)
		");

		$file = $_SERVER["DOCUMENT_ROOT"].self::CSV_FILE;
		if(!file_exists($file)){
			exit('файла нет!');
		}

		$n = -1;
		if (($handle = fopen($file, "r")) !== FALSE) {
		    while (($data = fgetcsv($handle, 3000, ";")) !== FALSE) {
		        $n++;
		        if(!$n) continue;
		        
		        $this->app->query("INSERT IGNORE INTO `dpd_city_cost` SET `CITY_NAME` = '".$this->dbHelper->forSql(trim($data[0]))."'");
		        fwrite(STDOUT, $n."\r");
		    }
		    fclose($handle);
		}

		return $this;
	}

	public function process(){
		$this->setCalcClient();

		do{
			$res = $this->calculateCities();
		}while($res);
	}

	public function calculateCities(){

		if(!$res = $this->app->query("
			SELECT * FROM `dpd_city_cost` WHERE `STATUS` = 0 ORDER BY `ID` ASC LIMIT 5")){
			return false;
		}

		while($result = $res->Fetch()){
			
			if(!$rsCity = CityesTable::getList(["filter" => ["CITY_NAME" => $result["CITY_NAME"]]])->Fetch()){
				$this->app->query("
					UPDATE `dpd_city_cost`
					SET
						`STATUS` = 1,
						`RESULT` = 'Не найден город'
					WHERE
						`ID` = ".$result["ID"]."
				");
				continue;
			}
			
			$arCosts = [];
			$arLost = [];
			foreach($this->arWeights as $weight){					
				if(!$arCosts[$weight] = $this->getPrice($rsCity["CITY_ID"], $weight)){
					$arLost[] = $weight;
				}
				fwrite(STDOUT, "in process ".$rsCity["CITY_NAME"]." - w.".$weight." прошло: ".$this->getWasteTime()." сек."."\r");
			}

			fwrite(STDOUT, "\n");

			$query = "UPDATE `dpd_city_cost` SET `STATUS` = 1, `COSTS` = '".serialize($arCosts)."' ";

			if(!empty($arLost)){
				$query .= ",`RESULT` = 'не обработаны веса: ".implode("; ", $arLost)."'";
			}

			$query .= " WHERE `ID` = ".$result["ID"];

			$this->app->query($query);
		}

		return true;
	}

	public function getPrice($cityID, $weight){
	    
	    $arData = array(
	        'delivery' => array(            // город доставки
	            'cityId' => $cityID, //id города
	            // 'cityName' => 'Самара', //сам город
	        ),
	        'auth' => array(
	            'clientNumber' => self::MY_NUMBER,
	            'clientKey' => self::MY_KEY
	        )
	    );
	    if ($this->sposob == 'home'){ //если отправляем до дома то ставим значение false
	        $arData['selfDelivery'] = false;// Доставка ДО дома
	    }else { // если же мы хотим отправить до терминала то true
	        $arData['selfDelivery'] = true;// Доставка ДО терминала
	    }

	    $arData['pickup'] = array(
	        'cityId' => 49694102,
	    ); // где забирают товар
	            
	    // что делать с терминалом
	    $arData['selfPickup'] = true;// Доставка ОТ терминала // если вы сами довозите до терминала то true если вы отдаёте от двери то false
	    $arData['parcel'] = array(
	        'weight' => $weight / 1000,
	        'length' => 0.01,
	        'width' => 0.01,
	        'height' => 0.01,
	        'quantity' => 1
	    );
	    $arData['declaredValue'] = 10000; //Объявленная ценность (итоговая)
	    $arRequest['request'] = $arData; // помещаем в массив запроса 
	    // var_dump(json_encode($arRequest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	    // 	exit();
	    try{
	    	$res = $this->client->getServiceCostByParcels($arRequest); //делаем сам запрос
	    	
	    }catch(\Exception $e){
	    	var_dump($e->getMessage());
	    	// exit();
	    	return false;
	    }
	    
	    $result = false;
	    foreach($res->return as $data){
	        if($data->serviceName == 'DPD Online Express'){
	            $result["cost"] = $data->cost;
	            $result["days"] = $data->days;
	        }
	    }
	    return $result;
	}

	public function genNewCsv(){
		$file = $_SERVER["DOCUMENT_ROOT"].self::CSV_FILE;
		if(!file_exists($file)){
			exit('файла нет!');
		}

		$this->app->query("UPDATE `dpd_city_cost` SET STATUS = 1");

		$dir = $_SERVER["DOCUMENT_ROOT"]."/test/dpd/";
		$arFiles = array_diff(scandir($dir), [".", ".."]);
		foreach($arFiles as $files){
			if(preg_match("/new_file/", $files)){
				$last = (int) str_replace(["new_file_", ".csv"], [""], $files);
			}
		}

		if(!$last) $last = 0;

		$last++;

		$fileopen=fopen($_SERVER["DOCUMENT_ROOT"]."/test/dpd/new_file_".$last.".csv", "a+");
		
		$columns = [];

		$n = -1;
		if (($handle = fopen($file, "r")) !== FALSE) {
		    while (($data = fgetcsv($handle, 3000, ";")) !== FALSE) {
		        $n++;
		        if(!$n){
		        	foreach($this->arWeights as $w){
		        		$data[] = $w;
		        		$data[] = "Срок";
		        	}
		        	$columns = $data;
		        }else{
		        	if($rs = $this->app->query("SELECT * FROM `dpd_city_cost` WHERE `CITY_NAME` = '".trim($data[0])."' AND `STATUS` = 1")->Fetch()){
		        		$arCosts = unserialize($rs["COSTS"]);
		        		
			        	foreach($arCosts as $k => $cost){
			        		$key = array_search($k, $columns);
			        		$data[$key] = $cost["cost"];
			        		$data[$key+1] = $cost["days"];
			        	}			        	
			        	$this->app->query("UPDATE `dpd_city_cost` SET `STATUS` = 2 WHERE `ID` = ".$rs["ID"]);	
		        	}else{
		        		continue;
		        	}
		        	
		        }

		        $write = implode(";", $data)."\r\n";
				fwrite($fileopen,$write);
		        fwrite(STDOUT, $n."\r");
		    }
		    fclose($fileopen);
		    fclose($handle);
		}
	}
}
