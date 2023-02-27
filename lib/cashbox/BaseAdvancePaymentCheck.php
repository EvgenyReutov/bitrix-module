<?php

namespace Instrum\Main\Cashbox;


class BaseAdvancePaymentCheck extends \Bitrix\Sale\Cashbox\AdvancePaymentCheck
{
    /**
     * @return string
     */
    public static function getType()
    {
        $trace = debug_backtrace();
        if(
            !empty($trace) &&
            !empty($trace[1]) &&
            !empty($trace[1]['function']) &&
            $trace[1]['function'] == 'buildCheckQuery'
        ) {
            return 'advancepayment';
        }

        return 'baseadvancepayment';
    }
}