<?php
/**
 * Created by PhpStorm.
 * User: aleks.omelich
 * Date: 25/11/2019
 * Time: 23:18
 */

namespace Instrum\Main\Order;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;

class Nps
{
    const TRACE_ID = 'NPS';
    const AGENT_FUNC = '\Instrum\Main\Order\Nps::fromAgent();'; // not used, should be removed
    const CANCEL_STATUS = 'CA';
    const SUCCESS_STATUS = 'F';

    protected static $time_borders = [10, 21];
    //protected static $finished_status = ['F', 'CA'];

    public static $type = 'success'; //mixed/success/cancel (default value - mixed)

    public static function getOrderFilter()
    {
        $result = ['ID' => -1];
        $time_start = Option::get("local.main", "NPS_start", false);
        if(!$time_start)
        {
            $time_start = date("j.m.Y H:i:s");
            Option::set("local.main", "NPS_start", $time_start);
        }
        switch (self::$type)
        {
            case 'cancel':
                $left_time_border = date("j.m.Y H:i:s", strtotime("-28 days"));
                $right_time_border = date("j.m.Y H:i:s", strtotime("-2 hours"));

                $trace_filter = [
                    '>'.TraceTable::DATE => $left_time_border,
                    TraceTable::TYPE => static::TRACE_ID
                ];

                $mailed_orders = [];
                foreach(TraceTable::yieldList(['filter' => $trace_filter]) as $item)
                {
                    $mailed_orders[] = $item[TraceTable::ORDER_ID];
                }

                $result = [
                    '><DATE_UPDATE' => [$left_time_border, $right_time_border],
                    '>DATE_UPDATE' => $time_start,
                    '!ID' => $mailed_orders,
                    //'STATUS_ID' => static::$finished_status,
                    "STATUS_ID" => self::CANCEL_STATUS
                ];
                break;
            case 'success':

                $left_time_border = date("j.m.Y H:i:s", strtotime("-28 days"));
                $right_time_border = date("j.m.Y H:i:s", strtotime("-3 days"));

                $trace_filter = [
                    '>'.TraceTable::DATE => $left_time_border,
                    TraceTable::TYPE => static::TRACE_ID
                ];

                $mailed_orders = [];
                foreach(TraceTable::yieldList(['filter' => $trace_filter]) as $item)
                {
                    $mailed_orders[] = $item[TraceTable::ORDER_ID];
                }

                $result = [
                    '><DATE_UPDATE' => [$left_time_border, $right_time_border],
                    '>DATE_UPDATE' => $time_start,
                    '!ID' => $mailed_orders,
                    //'STATUS_ID' => static::$finished_status,
                    "STATUS_ID" => self::SUCCESS_STATUS,
                    "!DELIVERY_ID" => DELIVERY_TK_ARRAY
                ];
                break;
        }
        return $result;
    }

    public static function checkOrders()
    {
        if(date("H") < static::$time_borders[0] || date("H") >= static::$time_borders[1]) return false;

        $new_orders_filter = self::getOrderFilter();

        $rs = Order::getList([
            'limit' => 10,
            'filter' => $new_orders_filter
        ]);
        //$count = $rs->getSelectedRowsCount();
        while($arOrder = $rs->fetch())
        {
            static::releaseOrder($arOrder['ID'], $arOrder['USER_ID']);
        }

        return true;
    }

    public static function releaseOrder($orderId, $userId)
    {
        $user = \CUser::GetByID($userId)->Fetch();
        $name_path = [];

        if(!empty($user['NAME'])) {
            $name_path[] = preg_replace ("/[^a-zA-ZА-Яа-я-ё\s]/iu",'', $user['NAME']);
        }
        if(!empty($user['LAST_NAME'])) {
            $name_path[] = preg_replace ("/[^a-zA-ZА-Яа-я-ё\s]/iu",'', $user['LAST_NAME']);
        }

        \CEvent::Send(
            'NPS_ORDER_FINISH',
            's1',
            [
                'EMAIL' => $user['EMAIL'],
                'USER_FULL' => implode(' ', $name_path),
                'ORDER_ID' => $orderId,
                'USER_ID' => $userId
            ]
        );
        TraceTable::add(
            [
                TraceTable::ORDER_ID => $orderId,
                TraceTable::TYPE => self::TRACE_ID,
                TraceTable::VALUE => 'sent',
                TraceTable::DATE => new DateTime()
            ]
        );
    }

    public static function fromAgent($type = 'success')
    {
        self::$type = $type;
        static::checkOrders();
        return "\Instrum\Main\Order\Nps::fromAgent('".$type."');";
        //return static::AGENT_FUNC;
    }
}