<?php

namespace Instrum\Main\Order\Delivery;

use COption;
use DateTime;
use Instrum\Main\Order\Delivery;
use SimpleXMLElement;

class Dalli implements Delivery
{
    const API_ENDPOINT = 'https://api.dalli-service.com/v1/';
    const TOKEN_OPTION = 'DALLI_TOKEN';
    const MODULE_ID = 'dalliservicecom.delivery';
    const DATE_FORMAT = 'Y-m-d';

    const STATUS_SUCCESS = 'COMPLETE';
    const STATUS_RETURNED = ['RETURNED', 'RETURNING'];

    /**
     * @return bool|string|null
     */
    protected function getToken()
    {
        return COption::GetOptionString(self::MODULE_ID, self::TOKEN_OPTION);
    }

    /**
     * @param DateTime $from
     * @param DateTime $to
     * @return string
     */
    protected function prepareRequestBody($from, $to)
    {
        $xml = new SimpleXMLElement('<statusreq></statusreq>');
        $xml->addChild('auth')->addAttribute('token', $this->getToken());
        //$xml->addChild('done', 'ONLY_DONE');
        $xml->addChild('done', '');
        $xml->addChild('quickstatus', 'NO');
        $xml->addChild('datefrom', $from->format(self::DATE_FORMAT));
        $xml->addChild('dateto', $to->format(self::DATE_FORMAT));
        return $xml->asXML();
    }

    /**
     * @param $body
     * @return bool|string
     */
    protected function sendRequest($body)
    {
        $ch = curl_init(self::API_ENDPOINT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * @param $response
     * @return array
     */
    protected function readOrderNumbers($response)
    {
        $result = [
            'FINISHED' => [],
            'RETURNED' => []
        ];

        if (!empty($response)) {
            $xml = new SimpleXMLElement($response);
            foreach ($xml->order as $orderNode) {
                if ((string)$orderNode->status === self::STATUS_SUCCESS) {
                    $orderNo = (string)$orderNode->attributes()->orderno;
                    if (is_numeric($orderNo)) {
                        $result['FINISHED'][] = $orderNo;
                    }
                } elseif (in_array((string)$orderNode->status, self::STATUS_RETURNED)) {
                    $orderNo = (string)$orderNode->attributes()->orderno;
                    if (is_numeric($orderNo)) {
                        $result['RETURNED'][] = $orderNo;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getFinishedOrders($from, $to)
    {
        $xmlText = $this->prepareRequestBody($from, $to);
        $response = $this->sendRequest($xmlText);
        return $this->readOrderNumbers($response);
    }
}