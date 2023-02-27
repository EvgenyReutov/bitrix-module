<?php


namespace Instrum\Main\Order;

use CSaleOrder;
use DateTime;
use Instrum\Main\Order\Delivery\Dalli;

class DeliveryStatusService
{
    const STATUS_FINISHED = 'F';
    const STATUS_RETURNED = 'VC';


    /**
     * @return Delivery[]
     */
    protected function getDeliveries()
    {
        return [
            new Dalli()
        ];
    }

    protected function checkOrdersStatus($orderIds, $checkStatus)
    {
        $orders = CSaleOrder::GetList(
            [],
            [
                'ID' => $orderIds
            ]
        );

        while ($order = $orders->Fetch()) {
            if ($order['STATUS_ID'] !== $checkStatus) {
                CSaleOrder::StatusOrder($order['ID'], $checkStatus);
            }
        }
    }


    public function run()
    {
        $from = (new DateTime())->modify('-14 days');
        $to = new DateTime();

        $orderIds = [
            'FINISHED' => [],
            'RETURNED' => []
        ];
        foreach ($this->getDeliveries() as $delivery) {
            $orderArr = $delivery->getFinishedOrders($from, $to);
            $orderIds['FINISHED'] = $orderIds['FINISHED'] + $orderArr['FINISHED'];
            $orderIds['RETURNED'] = $orderIds['RETURNED'] + $orderArr['RETURNED'];
        }

        $orderIds['FINISHED'] = array_unique(array_filter($orderIds['FINISHED']));
        $orderIds['RETURNED'] = array_unique(array_filter($orderIds['RETURNED']));
        
        $this->checkOrdersStatus($orderIds['FINISHED'], self::STATUS_FINISHED);
        $this->checkOrdersStatus($orderIds['RETURNED'], self::STATUS_RETURNED);
    }


}