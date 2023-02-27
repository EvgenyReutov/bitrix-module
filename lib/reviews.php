<?php

namespace Instrum\Main;

use Bitrix\Main\Loader;

Loader::includeModule('iblock');

class Reviews
{
    
    const BLOCK_ID_REVIEWS = 17;
    const BLOCK_ID_QA = 18;
    
    protected $answer;
    protected $reviewsAnswer;
    protected $questionAnswer;

    public $errors;
    private $compact = false;

    private $dataLayer = array();

    public function __construct()
    {
        try {
            if(!isset($_POST["productID"]) || intval($_POST["productID"]) <= 0){
                throw new \Exception("не указан код товара");
            }
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'getForm':
                        $this->getReviewFormHTML();
                        $this->getQuestionFormHTML();
                        break;
                    case 'saveOrder':
                        $this->saveOrder();
                        break;
                }
            } else {
                throw new \Exception('не указано действие');
            }
        } catch (\Exception $e) {
            $this->answer = '
				<span style="font-size: 14px; color: red;">Ошибка на сервере.<br>' . $e->getMessage() . '<br>повторите попытку позже.</span>
			';
        }
    }

    private function saveOrder()
    {
        switch ($_REQUEST["ENTITTY"]){
            case 'reviews':
                if (empty($_POST["fields"]["firstname"]) || empty($_POST["fields"]["comment"])) {
                    $this->errors[] = "Все поля должны быть заполнены";
                }
                if(empty($_POST["MARK"]) || intval($_POST["MARK"]) <= 0){
                    $this->errors["MARK"] = "нужно оценить товар";
                }
                break;
            case 'question':
                if (
                    empty($_POST["fields"]["firstname"]) ||
                    empty($_POST["fields"]["email"]) ||
                    empty($_POST["fields"]["question"])
                ) {
                    $this->errors[] = "Все поля должны быть заполнены";
                }
                break;
        }

        if (!empty($this->errors)) {
            $this->getReviewFormHTML();
            $this->getQuestionFormHTML();
        } else {
            if ($this->save($_REQUEST["ENTITTY"])) {
                $this->getSuccessHTML($_REQUEST["ENTITTY"]);
            } else {
                throw new \Exception("не удалось сохранить заказ");
            }
        }
    }

    private function save($entity){
        $result = false;
        switch ($entity){
            case 'reviews':
                $result = $this->saveReview();
                break;
            case 'question':
                $result = $this->saveQuestion();
                break;
        }

        return $result;
    }

    private function saveReview(){
        global $USER;
        $el = new \CIBlockElement;

        $PROP = array();
        $PROP["MARK"] = $_POST["MARK"];
        $PROP["AUTHOR"] = $_POST["fields"]["firstname"];
        if($USER->IsAuthorized())
        {
            $PROP["USER"] = $USER->GetID();
        }

        $PROP["DISADVANTAGE"] = $_POST["fields"]["disadvantages"];
        $PROP["ADVANTAGE"] = $_POST["fields"]["advantages"];
        $PROP["LINK"] = $_POST["productID"];
        $PROP["USER"] = $USER->GetID();

        $prod = \CIBlockElement::GetByID($_POST["productID"])->GetNext();
        $name = "Отзыв к ".$prod["NAME"];
        $artnumber = \CIBlockElement::GetProperty(
            $prod["IBLOCK_ID"],
            $prod["ID"],
            array("sort" => "asc"),
            Array("CODE"=>"CML2_ARTICLE")
        )->Fetch()["VALUE"];

        $PROP["ARTNUMBER"] = $artnumber;
        $PROP["URL"] = "https://".$_SERVER["SERVER_NAME"].$prod["DETAIL_PAGE_URL"];

        $arLoadProductArray = Array(
            "IBLOCK_ID"      => 17,
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => $name,
            "ACTIVE"         => "N",
            "PREVIEW_TEXT"   => $_POST["fields"]["comment"],
        );

        if($PRODUCT_ID = $el->Add($arLoadProductArray))
            return true;
        else
            return false;

    }
    private function saveQuestion(){
        global $USER;

        $el = new \CIBlockElement;

        $properties = [
            'AUTHOR' => $_POST['fields']['firstname'],
            'EMAIL' => $_POST['fields']['email'],
            'LINK' => $_POST['productID'],
            'USER' => $USER->GetID()
        ];

        $prod = \CIBlockElement::GetByID($_POST['productID'])->GetNext();
        $name = 'Вопрос к ' . $prod['NAME'];
        $artnumber = \CIBlockElement::GetProperty(
            $prod['IBLOCK_ID'],
            $prod['ID'],
            ['sort' => 'asc'],
            ['CODE' => 'CML2_ARTICLE']
        )->Fetch()['VALUE'];

        $properties['ARTNUMBER'] = $artnumber;
        $properties['URL'] = 'https://'.$_SERVER["SERVER_NAME"] . $prod['DETAIL_PAGE_URL'];

        $arLoadProductArray = [
            'IBLOCK_ID' => 18,
            'PROPERTY_VALUES' => $properties,
            'NAME' => $name,
            'ACTIVE' => 'N',
            'PREVIEW_TEXT' => $_POST['fields']['question'],
        ];

        $productId = $el->Add($arLoadProductArray);
        return !empty($productId);
    }

    private function getSuccessHTML($entity)
    {
        $this->compact = true;

        switch($entity) {
            case 'reviews':
                $this->answer = '
                    
                    <span class="header-text">Спасибо!</span>
                    <span class="content-text" style="font-size: 15px;
    font-weight: 400;">Мы проверим и опубликуем ваш отзыв в ближайшее время.</span>
                    <a class="back js-close_quickOrder" href="javascript:void(0)">ок</a>
                    ';
                break;
            case 'question':
                $this->answer = '
                    
                    <span class="header-text">Спасибо!</span>
                    <span class="content-text" style="font-size: 15px;
    font-weight: 400;"> Вопрос успешно добавлен.<br>Мы ответим и опубликуем ваш вопрос в ближайшее время.</span>
                    <a class="back js-close_quickOrder" href="javascript:void(0)">ок</a>
                    ';
                break;
        }

    }

    public function isCompact()
    {
        return $this->compact;
    }

    private function getReviewFormHTML()
    {
        $this->reviewsAnswer = '';
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                // $this->reviewsAnswer .= '<span style="font-size: 14px; color: red;">' . $error . '</span><br>';
            }
        }

        $arReplacement = [
            "#fields[firstname]#",
            "#fields[comment]#",
            "#fields[advantages]#",
            "#fields[disadvantages]#",
            "#productID#",
            "#MARK#",
            "#MARK_ERROR#"
        ];

        $arReplace = [
            $_POST["fields"]["firstname"],
            $_POST["fields"]["comment"],
            $_POST["fields"]["advantages"],
            $_POST["fields"]["disadvantages"],
            $_POST["productID"],
            $_POST["MARK"],
        ];

        if(isset($this->errors["MARK"])){
            $arReplace[] = '<div class="error"><span>'.$this->errors["MARK"].'</span><i class="fa fa-exclamation error-ico active" style="color: red;margin-left: 10px;"></i></div>';
        }else{
            $arReplace[] = '';
        }

        $this->reviewsAnswer .= str_replace(
            $arReplacement,
            $arReplace,
            file_get_contents(__DIR__."/../page/review.php")
        );
    }

    private function getQuestionFormHTML(){
        $this->questionAnswer = '';
        if (!empty($this->errors)) {
            foreach ($this->errors as $error) {
                // $this->questionAnswer .= '<span style="font-size: 14px; color: red;">' . $error . '</span><br>';
            }
        }

        $this->questionAnswer .= str_replace(
            [
                "#fields[firstname]#",
                "#fields[email]#",
                "#fields[question]#",
                "#productID#",
            ],
            [
                $_POST["fields"]["firstname"],
                $_POST["fields"]["email"],
                $_POST["fields"]["question"],
                $_POST["productID"],
            ],
            file_get_contents(__DIR__."/../page/question.php")
        );
    }

    public function getReviewAnswer()
    {
        return $this->reviewsAnswer;
    }
    public function getQuestionAnswer()
    {
        return $this->questionAnswer;
    }
    public function getAnswer()
    {
        return $this->answer;
    }

    public static function setProductMark($productID, $mark){

        if(!$properties = \CIBlockProperty::GetList(
            ["sort"=>"asc", "name"=>"asc"],
            [
                "ACTIVE"=>"Y",
                "IBLOCK_ID" => CATALOG_IB,
                "CODE" => "MARK"
            ]
        )->Fetch()){
            $arFields = Array(
                "NAME" => "Оценка",
                "ACTIVE" => "Y",
                "SORT" => "600",
                "CODE" => "MARK",
                "PROPERTY_TYPE" => "N",
                "IBLOCK_ID" => CATALOG_IB,
            );
            $ibp = new \CIBlockProperty;
            $ibp->Add($arFields);
        }

        if(!\CIBlockElement::SetPropertyValueCode($productID, "MARK", $mark)){
            return false;
        }
        return true;
    }

    public static function getProductMark($ID)
    {
        $rs = \CIBlockElement::GetList(
            [],
            ["IBLOCK_ID" => CATALOG_IB, "ID" => $ID],
            false,false,
            ["PROPERTY_MARK"]
        )->Fetch()["PROPERTY_MARK_VALUE"];
        return $rs;
    }

    /**
     * @param $userId
     * @param $iblockId
     * @param string $userIdProperty
     * @param bool $activeOnly
     * @return array|bool
     */
    private static function getEntitiesByUserId($userId, $iblockId, $userIdProperty = 'PROPERTY_USER', $activeOnly = true)
    {
        if(!empty($userId) && !empty($iblockId)) {
            $result = [];
            $filter = [
                $userIdProperty => $userId,
                'IBLOCK_ID' => $iblockId,
            ];
            if($activeOnly) {
                $filter['ACTIVE'] = 'Y';
            }
            $rs = \CIBlockElement::GetList([], $filter, false, false, ['ID']);
            while($element = $rs->GetNext()) {
                $result[] = $element['ID'];
            }

            return $result;
        }

        return false;
    }

    /**
     * @param int $userId
     * @param bool $activeOnly
     * @return array|bool
     */
    public static function getReviewsByUserId($userId, $activeOnly = true)
    {
        return self::getEntitiesByUserId($userId, self::BLOCK_ID_REVIEWS, 'PROPERTY_USER', $activeOnly);
    }

    /**
     * @param int $userId
     * @param bool $activeOnly
     * @return array|bool
     */
    public static function getQuestionsByUserId($userId, $activeOnly = true)
    {
        return self::getEntitiesByUserId($userId, self::BLOCK_ID_QA, 'CREATED_BY', $activeOnly);
    }
}
