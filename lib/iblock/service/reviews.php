<?php

namespace Instrum\Main\Iblock\Service;

use Bitrix\Main\EventManager;

class Reviews{
    const IBLOCK_CODE = 'reviews';
    private static $iblock_id;

    public static function getIblockId(){
        if(!self::$iblock_id)
        {
            self::$iblock_id = \CIBlock::GetList(
                [],
                ["CODE" => self::IBLOCK_CODE]
            )->Fetch()["ID"];
        }

        return self::$iblock_id;
    }

    public static function getPropertyId($code)
    {
        if($property = \CIBlockProperty::GetList([], ['CODE' => $code, 'IBLOCK_ID' => self::getIblockId()])->GetNext())
        {
            return $property['ID'];
        }
        return false;
    }

    public static function installEvents()
    {
        EventManager::getInstance()->registerEventHandler(
            "iblock",
            "OnBeforeIBlockElementUpdate",
            "local.main",
            "\Instrum\Main\Iblock\Service\Reviews",
            "OnUpdate",
            100,
            "",
            [
                'IBLOCK_ID' => self::getIblockId(),
            ]
        );
    }

    protected static function propsToArray($property_values)
    {
        if($keys = array_keys($property_values))
        {
            $props = [];
            $rs = \CIBlockProperty::GetList([], [
                'IBLOCK_ID' => self::getIblockId(),
                'PROPERTY_ID' => $keys
            ]);

            while($property = $rs->GetNext())
            {
                $propData = $property_values[$property['ID']];
                $value = $propData[array_keys($propData)[0]]['VALUE'];

                if(is_array($value) && isset($value['TEXT']))
                    $value = $value['TEXT'];

                $props[$property['CODE']] = $value;
            }

            return $props;
        }

        return false;
    }

    public static function getHost()
    {
        return \CSite::GetList($by = 'id', $order='asc', ['DEFAULT' => 'Y'])->GetNext();
    }

    public static function OnUpdate($iblock_id, $arFields)
    {
        if($iblock_id == $arFields['IBLOCK_ID'])
        {
            if($props = self::propsToArray($arFields['PROPERTY_VALUES']))
            {
                if(
                    $arFields['ACTIVE'] == 'Y'
                    && ($userId = $props['USER'])
                    && ($link = $props['LINK'])
                )
                {
                    $host = self::getHost();
                    $url = $host['SERVER_NAME'] ? 'http://'.$host['SERVER_NAME'] : 'https://'.$_SERVER["SERVER_NAME"];
                    $arUser = \CUser::GetByID($userId)->GetNext();
                    $arLink = \CIBlockElement::GetByID($link)->GetNext();

                    $props['TEXT'] = $arFields['PREVIEW_TEXT'];
                    $props['EMAIL'] = $arUser['EMAIL'];

                    $props['PRODUCT_NAME'] = $arLink['NAME'];
                    $props['URL'] = $url.$arLink['DETAIL_PAGE_URL'];
                    $props['REVIEW_URL'] = $url.$arLink['DETAIL_PAGE_URL'];

                    \CEvent::Send("REVIEW_ACCEPTED", $host['LID'], $props);

                }

            }
        }
    }
}