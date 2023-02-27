<?php

namespace Instrum\Main\Catalog;

/**
 * когда попали в КТ, определяем город и соответствующие склады самовывоза.
 * например мы в москве, и нам доступны личные склады самовывоза:
 * - федюково
 * - брати
 * - ясно
 * 
 * проходимся по каждому из них и определяем количество.
 * 
 * например текущая картна такая:
 * 
 * - федюково (22)
 * - брати (150)
 * - ясно (213)
 * 
 * и нам требуется 170 единиц товара.
 * 
 * тогда мы проходимся по каждому и смотрим, ага на первом складе у нас недостаточно
 * на втором тоже не достаточно
 * на третьем в самый раз.
 * 
 * тогда мы у третьего склада пишем что можем забрать сегодня, а первые два придется считать
 * 
 * далее уже мы зная у товара схему обеспечения, мы можем по каждому складу пройтись
 * 
 * например у нас схема БОШ, внутри этой схемы мы видим что у федюково есть цепочка
 * и у этой цепочки только один элемент (склад).
 * 
 * записываем его срок и смотрим сколько есть на нем.
 * 
 * т.е. нам надо еще 148 штук, смотрим, есть ли тут столько, допустим что тут есть только 130.
 * тогда мы помечаем что срок доставки становится 1 день и осталось найти 148 - 130 = 18 штук.
 * если осталось не 0 то смотрим дальше.
 * 
 * если у нас есть в цепочке еще склад, то бежим туда и делаем ве до тех пор пока не упремся в конец, 10й склад.
 * если такое произошло, или как в нашем случае первый склад был последний, то предварительно установив срок 10 дней в любой склад из мега-склада.
 * ты к нашему текущему сроку подписываем +10 и выводим результат на экран
*/

use Instrum\Main\Tools\ftp;
use \Bitrix\Main\Application;
use \Bitrix\Main\Loader;
use \Bitrix\Sale\Location\GeoIp;

use \Bitrix\Iblock\ElementTable;
use \Bitrix\Catalog\StoreTable;
use \Bitrix\Catalog\StoreProductTable;

use Instrum\Main\Table\Matrix\ProductsTable;
use Instrum\Main\Table\Matrix\SchemesTable;
use Instrum\Main\Table\Matrix\SchemesDetailTable;
use Instrum\Main\Table\Matrix\DeliveriesTable;
use Instrum\Main\Table\Matrix\StoresTable;

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/classes/general/xml.php');

Loader::includeModule('catalog');
Loader::includeModule('iblock');
Loader::includeModule('sale');

class Matrix
{
	const FTP_URL = "mysite.ru";
	const FTP_USER = "1c_api";
	const FTP_PASS = "ZxwIZe5NanA";

	const TBL_PREFIX = '1c_api_';
	const TBL_PRODUCTS = 'products';
	const TBL_SCHEMES = 'schemes';
	const TBL_SCHEMES_DETAIL = 'schemes_detail';
	const TBL_DELIVERIES = 'deliveries';
	const TBL_STORES = 'stores';

	const EMPTY_DELIVERY_PERIOD = 10; // срок поставки товара при его отсутствии на всех складах

    const IBLOCK_ID = 20;

	// обработка файлов именно в таком порядке, поскольку они друг от друга зависимы
	public $arFiles = [
		"Schemes" => "СхемыОбеспечения.XML",
		"DeliveryVariants" => "СпособыДоставки.XML",
		"RegionStores" => "СкладРегион.XML",
		"RestPrice" => "ОстаткиИЦены.XML",
	];

	public $arStores = [];
	public $ftp;
	public $city;
	public $arElement;

	private $currentScheme = false;

	public $defaultCityCode = "0000073738";
	# public $defaultCityCode = "0000032609"; // Подольск для тестов

	public function __construct(){		
		$this->ftp = new ftp(self::FTP_URL); 
		if(!$this->ftp->ftp_login(self::FTP_USER, self::FTP_PASS)){
			throw new \Exception("Не удалось подключиться к ".self::FTP_URL);
		}

		$this->app = Application::getConnection();
		$this->dbHelper = $this->app->getSqlHelper();

		$this->getCurrentCity();
	}

	/**
	 * получаем данные по товару и его текущие остатки по складам
	 * чекаем каждый активный склад
	 */
	public function getStoresForProduct($productID, $quantity = 1){

		$productTableName = ProductsTable::getTableName();
		if(!$this->arElement = ElementTable::getList([
			"filter" => [
				$productTableName.".ID" => $productID,
			],
			"select" => [
				"ID", "XML_ID",
				"SCHEME_ID" => $productTableName.".SCHEME_ID"
			],
			"runtime" => [
				$productTableName => [
		            'data_type' => ProductsTable::getEntity(),
		            'reference' => [
		                '=this.ID' => 'ref.ID'
		            ],
		            'join_type' => 'INNER JOIN'
		        ]
		    ]
		])->Fetch()){
			return false;
		}

		// получаем склады, которые можно использовать в городе/регионе и остаток товара на этих складах

		$arStores = $this->getStores($productID);

		foreach($arStores as &$store){
			$store["REMAINING"] = -$quantity;
			$this->getStorePeriodByScheme($store);
		}

		// ко всем рассчитанным складам добавить доступные доставки посмоттреть в сторону генераторов и yeld
		
		$storesTableName = StoresTable::getTableName();
		$rsDeliveries = DeliveriesTable::getList([
			"filter" => [
				$storesTableName.".STORE_ID" => array_keys($arStores)
			],
			"select" => [
				"NAME",
				"STORE_ID" => $storesTableName.".STORE_ID",
			],
			"runtime" => [
				$storesTableName => [
					"data_type" => StoresTable::getEntity(),
					"reference" => [
						"=this.ID" => "ref.DELIVERY_ID"
					],
					"join_type" => "INNER JOIN"
				]
			]
		]);

		while($result = $rsDeliveries->Fetch()){
			if(isset($arStores[$result["STORE_ID"]])){
				$arStores[$result["STORE_ID"]]["AVAILABLE_DELIVERIES"][] = $result["NAME"];
			}
		}
		return $arStores;
	}

	private function getStores($prodID){
		$storesTableName = StoresTable::getTableName();
		$storeProductTableName = StoreProductTable::getTableName();
		$schemeDetailTableName = SchemesDetailTable::getTableName();

		$rsStore = StoreTable::getList([
			"filter" => [
				"!".$storesTableName.".REGION_RU" => false,
				$storesTableName.".CITY_RU" => $this->city,
				
			],
			"select" => [
				"TITLE", "XML_ID", "ID",
				"DELIVERY_ID" => $storesTableName.".DELIVERY_ID",
				"PERIOD" => $storesTableName.".PERIOD",
				"REGION_RU" => $storesTableName.".REGION_RU",
				"CITY_RU" => $storesTableName.".CITY_RU",
				"CHAIN" => $schemeDetailTableName.".CHAIN"
			],
			"runtime" => [
				$storesTableName => [
					'data_type' => StoresTable::getEntity(),
					'reference' => [
						'=this.ID' => 'ref.STORE_ID'
					],
					'join_type' => 'INNER JOIN'
				],
				$schemeDetailTableName => [
					'data_type' => SchemesDetailTable::getEntity(),
					'reference' => [
						'=this.ID' => 'ref.STORE_ID'
					],
					'join_type' => 'INNER JOIN'
				],
			],
		]);

		$arStores = [];
		while($result = $rsStore->Fetch()){
			$result["CHAIN"] = unserialize($result["CHAIN"]);
			$arStores[$result["ID"]] = $result;
		}

		return $arStores;
	}

	/**
	 * получение цепочек поставок товара по складу
	 */
	private function getStorePeriodByScheme(&$store){
		/**
		 * определяем наличие товара на текущем складе
		 */
		
		$dbResult = \CCatalogStore::GetList(
		   [],
		   ['PRODUCT_ID' => $this->arElement["ID"], "ID" => $store["ID"]],
		   false,
		   false,
		   ["*", "PRODUCT_AMOUNT"]
		)->Fetch();

		// устанавливаем количество в оставшееся
		$store["REMAINING"] = $store["REMAINING"] + $dbResult["PRODUCT_AMOUNT"];

		// если осталось меньше нуля, значит определяем схему обеспечения у текущего товара
		// в этой схеме находим ту цепочку которая для текущего склада. если таковой нет, то ставим максимальный срок доставки

		if($store["REMAINING"] >= 0){
			return true;
		}

		// получим остатки по всей цепочке одним запросом

		$dbResult = \CCatalogStore::GetList(
		   [],
		   ['PRODUCT_ID' => $this->arElement["ID"], "ID" => array_keys($store["CHAIN"])],
		   false,
		   false,
		   ["*", "PRODUCT_AMOUNT"]
		);
		$chainStore = [];
		while($result = $dbResult->Fetch()){			
			$chainStore[$result["ID"]] = $result["PRODUCT_AMOUNT"];
		}
		
		// если у нас всего один вариант остатков и он равен 0, то ставим максимальный срок доставки и выходим
		if(count(array_unique($chainStore)) == 1 && current(array_unique($chainStore)) == 0){
			$store["PERIOD"] = self::EMPTY_DELIVERY_PERIOD;
			return true;
		}

		// проходимся по всей цепочке. считаем доступное количество на текущем складе и прибавляем к оставшемуся
		// если получили 0, т.е. может отдать все выходим иначе считаем дальше и увеличиваем сроки
		foreach($store["CHAIN"] as $k => $chain){			
			$store["REMAINING"] = $store["REMAINING"] + $chainStore[$k];
			$store["PERIOD"] = $store["PERIOD"] + $chain;
			if($store["REMAINING"] >= 0){
				break;
			}
		}

		if($store["REMAINING"] < 0){
			$store["PERIOD"] = self::EMPTY_DELIVERY_PERIOD;
		}
	}

	private function getCurrentCity(){
		$city = GeoIp::getLocationCode($_SERVER["REMOTE_ADDR"]);		
		$this->city = \Bitrix\Sale\Location\LocationTable::getByCode(
			!empty($city) ? $city : $this->defaultCityCode,
			[
			    'filter' => ['=NAME.LANGUAGE_ID' => LANGUAGE_ID],
			    'select' => ['*', 'NAME_RU' => 'NAME.NAME']
			]
		)->fetch();

		return $this;
	}

	/**
	 * получение даных по складам
	 */
	public function getStoresData($filter = []){
		$dbResult = \CCatalogStore::GetList(
		   [],
		   $filter,
		   false,
		   false,
		   ["*", "PRODUCT_AMOUNT"]
		);
		while($result = $dbResult->Fetch()){
			$this->arStores[$result["ID"]] = $result;
		}
		
		return $this;
	}

	/**
	 * обработка файлов на фтп
	 */
	public function obtainFiles(){
		$arList = $this->ftp->ftp_nlist("/");
		if(count($arList) <= 2){
			throw new \Exception("нет файлов для обработки в директории");			
		}

		foreach($this->arFiles as $k => $list){
			if(in_array($list, $arList)){
				$this->{'parse'.$k}($list);
			}
		}
		return $this;
	}

	/**
	 * по сути, данный файл уже обрабатывается с остатками, поэтому полностью не обрабатываем
	 * забираем только схемы обеспечения и пишем в табличку
	 */
	private function parseRestPrice($file){		
		$file = $this->ftp->saveTmpFile("/", $file);
		$xml = new \CDataXML();
		$xml->LoadString(file_get_contents($file));

		// поскольку схем не так много, и на каждой итерации делать запрос это кощунство
		// делаем по аналогии с доставками

		$arSchemes = [];

		if ($nodes = $xml->SelectNodes('/Товары')) {
    		foreach ($nodes->children() as $node) {
    			$xmlId = (string)$node->getAttribute('Идентификатор');
    			$schemeXmlID = (string)$node->getAttribute('ИдентификаторСхемаОбеспечения');

    			if(!$schemeId = array_search($schemeId, $arSchemes)){
            		if($schemeId = SchemesTable::getList(["filter" => ["XML_ID" => $schemeXmlID]])->Fetch()["ID"]){
            			$arSchemes[$schemeId] = $schemeXmlID;
            		}
            	}

            	$element = (\Bitrix\Iblock\ElementTable::getRow([
		            'filter' => [
		                '=XML_ID' => $xmlId,
                        '=IBLOCK_ID' => self::IBLOCK_ID
                    ],
		            'select' => ['ID']
		        ]))["ID"];

		        $stores = $node->elementsByName('Остатки')[0];
	            if ($stores) {
	                $stores = $stores->children();
	                foreach ($stores as $store) {
	                	if(!$rsStore = StoresTable::getList(
							["filter" => 
								["STORE_ID" => $this->getStoreFieldByXmlID($store->getAttribute('GUID'), "ID")]
							]
						)->Fetch()){
	                		$result = StoresTable::add([
		                		"STORE_ID" => $this->getStoreFieldByXmlID($store->getAttribute('GUID'), "ID"),
							]);
						}
	                }
	            }

            	if($element && !empty($schemeId)){
            		$result = ProductsTable::add([
	            		"ID" => $element,
	            		"SCHEME_ID" => $schemeId
	            	]);	
	            	if ($result->isSuccess()){
						$storeID = $result->getId();
						fwrite(STDOUT, $storeID."\r");
					}else{
						var_dump(__METHOD__." ".implode("\n", $result->getErrorMessages()));
					}
            	}
		        
    		}
    	}
	}

	/**
	 * парсинг файла СкладРегион
	 * дополняет таблицу складов, добавляя региональные привязки для них.
	 * для определения воможности использования склада в регионе
	 */
	private function parseRegionStores($file){
		$file = $this->ftp->saveTmpFile("/", $file);
		$xml = new \CDataXML();
		$xml->LoadString(file_get_contents($file));

		if ($nodes = $xml->SelectNodes('/СкладРегион')) {
			foreach ($nodes->children() as $node) {
				$storeXmlId = (string)$node->getAttribute('Идентификатор');
				$region = str_replace(["обл", " г"], ["область", ""], (string)$node->getAttribute('Регион'));
				$city = str_replace([" г"], [""], (string)$node->getAttribute('Город'));

				$rsStore = StoresTable::getList(
					["filter" => 
						["STORE_ID" => $this->getStoreFieldByXmlID($storeXmlId, "ID")]
					]
				);
				while($result = $rsStore->Fetch()){
					StoresTable::update($result["ID"], ["REGION_RU" => $region, "CITY_RU" => !empty($city) ? $city : $region]);
				}
			}
		}
	}

	/**
	 * парсинг файла СпособыДоставки
	 * определяем список доступных доставок и храним в таблице DeliveriesTable
	 * информацию по каждому складу и доступной доставке со сроком поставки храним в таблице StoresTable
	 */
	private function parseDeliveryVariants($file){
		$file = $this->ftp->saveTmpFile("/", $file);
		$xml = new \CDataXML();
		$xml->LoadString(file_get_contents($file));

		$arDeliveries = [];

		if ($nodes = $xml->SelectNodes('/СпособыДоставки')) {
			foreach ($nodes->children() as $node) {
				$storeXmlId = (string)$node->getAttribute('Идентификатор');

				$variants = $node->elementsByName('Склад');
				if($variants){
					foreach ($variants as $variant) {
	                	$delivery = $variant->getAttribute('СпособДоставкиСоСклада');
	                	$period = $variant->getAttribute('СрокДоставки');

	                	if(!$deliveryID = array_search($delivery, $arDeliveries)){
	                		if(!$deliveryID = DeliveriesTable::getList(["filter" => ["NAME" => $delivery]])->Fetch()["ID"]){
	                			$result = DeliveriesTable::add([									
									"NAME" => $delivery									
								]);
								if ($result->isSuccess()){
									$deliveryID = $result->getId();
									$arDeliveries[$deliveryID] = $delivery;
								}else{
									var_dump(__METHOD__." ".implode("\n", $result->getErrorMessages()));
								}
	                		}
	                	}

	                	$result = StoresTable::add([
	                		"STORE_ID" => $this->getStoreFieldByXmlID($storeXmlId, "ID"),
							"DELIVERY_ID" => $deliveryID,
							"PERIOD" => $period,							
						]);
						if ($result->isSuccess()){
							$storeID = $result->getId();
						}else{
							var_dump(__METHOD__." ".implode("\n", $result->getErrorMessages()));
						}
	                }
				}
			}
		}
	}

	/**
	 * парсинг файла СхемыОбеспечения
	 * группы схем храним в таблице SchemesTable
	 * склады и цепочки по складам каждой группы храним в SchemesDetailTable
	 * цепочка содержит в себе сериализованный массив. каждый элемент которого это ID склада (key) и срок поставки (value)
	 */
	private function parseSchemes($file){

		$file = $this->ftp->saveTmpFile("/", $file);
		$xml = new \CDataXML();
		$xml->LoadString(file_get_contents($file));

		if ($nodes = $xml->SelectNodes('/СхемыОбеспечения')) {
			foreach ($nodes->children() as $node) {
				$xmlId = (string)$node->getAttribute('Идентификатор');
				$name = (string)$node->getAttribute('СхемаОбеспечения');

				if(!$schemeID = SchemesTable::getList(["filter" => ["XML_ID" => $xmlId]])->Fetch()["ID"]){
					$result = SchemesTable::add([
						"XML_ID" => $xmlId,
						"NAME" => $name
					]);
					if ($result->isSuccess()){
						$schemeID = $result->getId();
					}else{
						var_dump(__METHOD__." ".implode("\n", $result->getErrorMessages()));
					}
				}

				if(!$schemeID){
					throw new \Exception("Не удалось обработать схему ".$name);					
				}

				$stores = $node->elementsByName('СкладGUID');

				if ($stores) {	                
	                foreach ($stores as $store) {
	                	$storeXmlId = $store->getAttribute('Идентификатор');
	                	$chain = $store->elementsByName('Цепочка')[0];
	                	if ($chain) {
	                		$res = true;
	                		$first = 0;
	                		$arChain = [];

			                do{
			                	$iteration = $first > 0 ? $first : '';
			                	$storeXml = $chain->getAttribute('Идентификатор'.$iteration);
			                	$period = $chain->getAttribute('Срок'.$iteration);
			                	if(empty($storeXml) || empty($period)){
			                		$res = false;
			                	}else{
			                		$storeID = $this->getStoreFieldByXmlID($storeXml, "ID");
			                		$arChain[$storeID] = $period;
			                		$first++;
			                	}
			                }while($res);
			            }

			            if($arChain){
			            	$result = SchemesDetailTable::add([
								"SCHEME_ID" => $schemeID,
								"STORE_ID" => $this->getStoreFieldByXmlID($storeXmlId, "ID"),
								"CHAIN" => serialize($arChain)
							]);
							if ($result->isSuccess()){
								$schemeDetailID = $result->getId();
							}else{
								var_dump(__METHOD__." ".implode("\n", $result->getErrorMessages()));
							}
			            }
	                }
	            }
			}
		}
	}

	/**
	 * получение поля склада по его xmlId
	 */
	public function getStoreFieldByXmlID($xmlId, $field){
		$result = false;
		foreach($this->arStores as $k => $store){
			if($store["XML_ID"] == $xmlId){
				$result = $store[$field];
			}
		}
		return $result;
	}

	/**
	 * создание таблиц для работы матрицы
	 */
	public function checkTables(){
		$this->app->query("DROP TABLE IF EXISTS `".self::TBL_PREFIX.self::TBL_PRODUCTS."`");
		$this->app->query("DROP TABLE IF EXISTS `".self::TBL_PREFIX.self::TBL_SCHEMES."`");
		$this->app->query("DROP TABLE IF EXISTS `".self::TBL_PREFIX.self::TBL_SCHEMES_DETAIL."`");
		$this->app->query("DROP TABLE IF EXISTS `".self::TBL_PREFIX.self::TBL_DELIVERIES."`");
		$this->app->query("DROP TABLE IF EXISTS `".self::TBL_PREFIX.self::TBL_STORES."`");

		// таблица для хранения данных по каждому товару
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `".self::TBL_PREFIX.self::TBL_PRODUCTS."`(
				`ID` INT NOT NULL PRIMARY KEY,				
				`SCHEME_ID` INT NOT NULL
			)
		");

		// таблица хранения общих данных по схемам
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `".self::TBL_PREFIX.self::TBL_SCHEMES."`(
				`ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				`XML_ID` VARCHAR(100) NOT NULL,				
				`NAME` VARCHAR(100)
			)
		");

		// таблица хранения подробных данных по схемам
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `".self::TBL_PREFIX.self::TBL_SCHEMES_DETAIL."`(
				`ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				`SCHEME_ID` INT NOT NULL,
				`STORE_ID` INT NOT NULL,
				`CHAIN` TEXT
			)
		");

		// таблица доставок по складам
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `".self::TBL_PREFIX.self::TBL_DELIVERIES."`(
				`ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				`NAME` VARCHAR(100)
			)
		");		

		// таблица со складами
		$this->app->query("
			CREATE TABLE IF NOT EXISTS `".self::TBL_PREFIX.self::TBL_STORES."`(
				`ID` INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
				`STORE_ID` INT NOT NULL,				
				`DELIVERY_ID` INT NOT NULL,
				`PERIOD` INT,
				`REGION_RU` VARCHAR(255),
				`CITY_RU` VARCHAR(255)
			)
		");
		return $this;
	}

	public function setSchemeID($id){
		$this->currentScheme = $id;
		return $this;
	}

	public function getSchemeID(){
		return $this->currentScheme;
	}
}