<?php


namespace Instrum\Main\Order;

use DateTime;

interface Delivery
{
    /**
     * @param DateTime $from
     * @param DateTime $to
     * @return array
     */
    public function getFinishedOrders($from, $to);
}