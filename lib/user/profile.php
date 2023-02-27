<?php

namespace Instrum\Main\User;

use Instrum\Ajax\Controller;
use Bitrix\Main\Loader;
use Bitrix\Sale;

Loader::includeModule('sale');

class Profile extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    public function setUserProfileAction()
    {
        if (intval($_REQUEST["personType"]) <= 0) {
            throw new \Exception("не указан тип плательщика personType");
        }
        $this->user->Update(
            $this->user->GetID(),
            [
                "UF_PERSON_TYPE" => $_REQUEST["personType"]
            ]
        );
        Controller::response(["success" => true]);
    }

    public function saveAction()
    {
        parse_str($_POST["postData"], $output);
        foreach ($output as &$p) {
            $p = htmlspecialchars($p);
        }

        /**
         * USER update
         */

        $arFields = [];

        if (isset($output["DELIVERY_ID"]) && intval($output["DELIVERY_ID"]) > 0) {
            $arFields["UF_DELIVERY"] = $output["DELIVERY_ID"];
        }
        if (isset($output["PAY_SYSTEM_ID"]) && intval($output["PAY_SYSTEM_ID"]) > 0) {
            $arFields["UF_PAY_SYSTEM"] = $output["PAY_SYSTEM_ID"];
        }

        $arFields["UF_NOTIFY_EMAIL"] = isset($output["UF_NOTIFY_EMAIL"]) ? '1' : '0';
        $arFields["UF_NOTIFY_SMS"] = isset($output["UF_NOTIFY_SMS"]) ? '1' : '0';
        $arFields["UF_NOTIFY_SALE"] = isset($output["UF_NOTIFY_SALE"]) ? '1' : '0';

        $arFields["UF_PVZ"] = $output["UF_PVZ"];

        if (isset($output["PERSONAL_CITY"])) {
            $arFields["PERSONAL_CITY"] = $output["PERSONAL_CITY"];
        }
        if (isset($output["BUYER_STORE"])) {
            $arFields["UF_STORE_ID"] = $output["BUYER_STORE"];
        }

        if (isset($output["PASS"]) && isset($output["RETRY_PASS"])) {
            if ($output["PASS"] !== $output["RETRY_PASS"]) {
                throw new \Exception("пароли не совпадают");
            }
            $arFields["PASSWORD"] = $output["PASS"];
            $arFields["CONFIRM_PASSWORD"] = $output["RETRY_PASS"];
        }

        if (count($arFields) > 0) {
            if (!$this->user->Update($this->user->GetID(), $arFields)) {
                throw new \Exception($this->user->LAST_ERROR);
            }
        }

        /**
         * PROFILE update
         */

        $arFields = [];

        if (strlen($output["NAME"]) > 0) {
            $arFields["NAME"] = trim($output["NAME"]);

            $saleProps = new \CSaleOrderUserProps;

            $saleProps->Update($output["PROFILE_ID"], ["NAME" => trim($arFields["NAME"])]);
        }

        foreach ($output as $k => $out) {
            if (preg_match("/ORDER_PROP/", $k)) {
                $arFields[str_replace("ORDER_PROP_", "", $k)] = trim($out);
            }
        }

        $updatedValues = array();
        $saleOrderUserPropertiesValue = new \CSaleOrderUserPropsValue;
        $userPropertiesList = $saleOrderUserPropertiesValue::GetList(
            ["SORT" => "ASC"],
            ["USER_PROPS_ID" => $output["PROFILE_ID"]],
            false,
            false,
            ["ID", "ORDER_PROPS_ID", "VALUE", "SORT", "PROP_TYPE"]
        );

        while ($propertyValues = $userPropertiesList->Fetch()) {

            if (isset($arFields[$propertyValues["ORDER_PROPS_ID"]])) {

                Sale\Internals\UserPropsValueTable::update(
                    $propertyValues["ID"],
                    array("VALUE" => $arFields[$propertyValues["ORDER_PROPS_ID"]])
                );
            }

            $updatedValues[$propertyValues["ORDER_PROPS_ID"]] = $arFields[$propertyValues["ORDER_PROPS_ID"]];
        }

        if ($newValues = array_diff_key($arFields, $updatedValues)) {
           foreach ($newValues as $orderPropId => $value) {
               $saleOrderUserPropertiesValue->Add(['VALUE' => $value, 'ORDER_PROPS_ID' => $orderPropId, 'USER_PROPS_ID' => $output["PROFILE_ID"]]);
            }
        }

        Controller::response($output);
    }

    /**
     * Delete id's files from property with type "File"
     * @param string $idFileDeletingList
     * @param array $baseArray
     * @return string $newValue
     */
    protected function deleteFromPropertyTypeFile($idFileDeletingList, $baseArray)
    {
        if (CheckSerializedData($idFileDeletingList)
            && ($serialisedValue = @unserialize($idFileDeletingList)) !== false)
        {
            $idFileDeletingList = $serialisedValue;
        }
        else
        {
            $idFileDeletingList = explode(';', $idFileDeletingList);
        }

        foreach ($idFileDeletingList as $idDelete)
        {
            $key = array_search($idDelete, $baseArray);
            if ($key !== false)
            {
                unset($baseArray[$key]);
            }
        }
        $newValue = serialize($baseArray);
        return $newValue;
    }
}