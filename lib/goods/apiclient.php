<?php


namespace Instrum\Main\Goods;

use \RuntimeException;

class ApiClient
{
    const ENDPOINT = 'https://partner.goods.ru/api/market/v1/orderService/';
    const TEST_ENDPOINT = 'https://test-partner.goods.ru/api/market/v1/orderService/';

    const JSON_DEFAULTS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK;

    /** @var string */
    protected $token;
    /** @var string */
    protected $endpoint;
    /** @var resource */
    protected $curl;

    /**
     * ApiClient constructor.
     * @param $token
     */
    public function __construct($token)
    {
        if(empty($token)) {
            throw new RuntimeException('GOODS token cannot be empty');
        }

        $this->token = $token;
        $this->endpoint = static::ENDPOINT;

        $this->initCurl();
    }

    /**
     *
     */
    public function __destruct()
    {
        if($this->curl) {
            curl_close($this->curl);
        }
    }

    /**
     *
     */
    protected function initCurl()
    {
        $this->curl = curl_init();

        if($this->curl === false) {
            throw new RuntimeException('Could not initialize CURL');
        }

        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($this->curl, CURLOPT_POST, 1 );
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    /**
     * @param string $method
     * @param array $data
     * @param array $meta
     * @return mixed
     */
    protected function request($method, $data = [], $meta = [])
    {
        $url = $this->endpoint . $method;
        $query = [
            'data' => array_merge($data, ['token' => $this->token]),
            'meta' => $meta
        ];

        curl_setopt($this->curl, CURLOPT_URL, $url);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, json_encode($query, self::JSON_DEFAULTS));

        $response = curl_exec($this->curl);
        if(!$response) {
            throw new RuntimeException(curl_error($this->curl));
        }

        $response = json_decode($response, true);
        if(empty($response)) {
            throw new RuntimeException('Empty response got');
        }

        if(empty($response['success'])) {
            throw new RuntimeException(empty($response['error']) ? 'Unknown error' : json_encode($response['error'], self::JSON_DEFAULTS));
        }

        return isset($response['data']) ? $response['data'] : null;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function orderSearch($filter)
    {
        $data = $this->request('order/search', $filter);
        return !empty($data['shipments']) ? $data['shipments'] : [];
    }

    /**
     * @param array $shipmentIds
     * @return mixed
     */
    public function orderGet($shipmentIds)
    {
        return $this->request('order/get', [
            'shipments' => $shipmentIds
        ]);
    }
}