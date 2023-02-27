<?php

namespace Instrum\Main\Events;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Sale\Internals\DiscountTable;

class DiscountAgent
{

    const USE_AGENT = 'refresh_cache_from_discounts';
    const FORCE_REFRESH = 'refresh_cache_from_discounts_force';
    const REFRESH_IBLOCKS = 'cache_iblocks';
    const MODULE_ID = 'local.main';


    public function clearCache($iblocks, $force = false)
    {
        \CIBlock::enableClearTagCache();
        foreach ($iblocks as $iblockId) {
            \CIBlock::clearIblockTagCache($iblockId);
        }
        \CIBlock::DisableClearTagCache();

        if($force)
        {
            BXClearCache(true);
            (new \Bitrix\Main\Data\ManagedCache())->cleanAll();
            (new \CStackCacheManager())->CleanAll();
        }
    }

    public function checkDiscounts()
    {
        $result = [];
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $rs = DiscountTable::getList([
            'select' => ['ID', 'ACTIVE_FROM', 'ACTIVE_TO', 'ACTIVE'],
            'filter' => [
                'ACTIVE' => 'Y',
                '!ACTIVE_TO' => False,
                '<=ACTIVE_TO' => date("d.m.Y H:m:i"),
            ]
        ]);

        while($discount = $rs->fetch())
        {
            $result[] = $discount['ID'];
        }

        return $result;
    }

    public function disableDiscounts($discounts)
    {
        foreach ($discounts as $discount)
        {
            DiscountTable::update($discount, ['ACTIVE' => 'N']);
        }
    }

    public static function listenDiscountChanges()
    {
        $use_agent = Option::get(self::MODULE_ID, self::USE_AGENT, false);
        $force = Option::get(self::MODULE_ID, self::FORCE_REFRESH, false);
        $iblocks = unserialize(Option::get(self::MODULE_ID, self::REFRESH_IBLOCKS, false));

        if($use_agent && $iblocks)
        {
            Loader::includeModule('iblock');
            Loader::includeModule('catalog');
            Loader::includeModule('sale');
            $agent = new DiscountAgent();
            if($discounts = $agent->checkDiscounts())
            {
                $agent->clearCache($iblocks, $force);
                $agent->disableDiscounts($discounts);
            }
        }

        return '\\' . __METHOD__ . '();';
    }

    public static function setup($install = true)
    {

        $period = 'Y';
        $interval = 3600;
        $exec = '\\'.__CLASS__.'::listenDiscountChanges();';

        if($install)
            \CAgent::AddAgent($exec, self::MODULE_ID, $period, $interval);
        else
            \CAgent::RemoveAgent($exec, self::MODULE_ID);
    }

}