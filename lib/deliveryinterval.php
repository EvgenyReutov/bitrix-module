<?php

namespace Instrum\Main;

use Bitrix\Main\Config\Option;
use \DateTime;
use \CIBlockElement;

class DeliveryInterval
{

    const DELIVERY_TYPE_SELF_PVZ = 1;
    const DELIVERY_TYPE_COURIER = 2;
    const DELIVERY_TYPE_DPD = 3;
    const DELIVERY_TYPE_BERU = 4;
    const DELIVERY_TYPE_DALLI = 5;
    const DELIVERY_TYPE_CDEK = 6;
    const DELIVERY_TYPE_SELF_PVZ_YASNOGORSKAYA = 7;
    const DELIVERY_TYPE_SELF_PVZ_VIDNOYE = 8;
    const DELIVERY_TYPE_CDEK_REGION = 9;
    const DELIVERY_TYPE_CDEK_EKB = 10;

    const MSK_WAREHOUSE_ID = 53;

    const MI_BRANDS = [
        'denzel',
        'elfe',
        'gross',
        'kronwerk',
        'matrix',
        'mtx',
        'noname',
        'palisad',
        'sparta',
        'stels',
        'stern',
        'alyumet',
        'amet',
        'arti',
        'baz',
        'bars',
        'gorizont',
        'ermak',
        'klever_s',
        'luga',
        'luga_abraziv',
        'niz',
        'praktika',
        'sibrtekh',
        'stroymash',
        'shurup',
        'rossiya',
        'glazov',
    ];

    protected $productId;
    protected $deliveryType;
    protected $defaultInterval;
    protected $useFallbackInterval = true;
    protected $deliveryMinInterval = false;

    protected static $cachedProductBrands = [];

    public function __construct($productId, $deliveryType = null)
    {
        $this->productId = is_array($productId) ? $productId : [$productId];
        $this->deliveryType = $deliveryType;
    }

    public function setDefaultInterval($date)
    {
        $this->defaultInterval = $date;
        return $this;
    }

    public function setDeliveryMinInterval($days)
    {
        $this->deliveryMinInterval = $days;
        return $this;
    }

    public function getDeliveryMinInterval()
    {
        return $this->deliveryMinInterval;
    }

    public function useFallbackInterval($use)
    {
        $this->useFallbackInterval = (bool)$use;
        return $this;
    }

    protected function isMainWarehouseDeduction()
    {
        return in_array(
            $this->deliveryType,
            [
                self::DELIVERY_TYPE_SELF_PVZ,
                self::DELIVERY_TYPE_COURIER,
                self::DELIVERY_TYPE_CDEK,
                self::DELIVERY_TYPE_SELF_PVZ_YASNOGORSKAYA,
                self::DELIVERY_TYPE_SELF_PVZ_VIDNOYE,
            ],
            false
        );
    }

    protected function existsOnMainWarehouse($productId)
    {
        static $cache = [];

        if(!array_key_exists($productId, $cache)) {
            $cache[$productId] = 0;

            $rowset = \CCatalogStoreProduct::GetList(
                [],
                [
                    'PRODUCT_ID' => $productId,
                    'STORE_ID' => self::MSK_WAREHOUSE_ID
                ],
                false,
                false,
                ['AMOUNT']
            );
            $row = $rowset->Fetch();
            if(!empty($row)) {
                $cache[$productId] = (int)$row['AMOUNT'];
            }
        }

        return $cache[$productId] > 0;
    }

    protected function updateResult($result, $interval)
    {
        if (!$result) {
            return $interval;
        }

        if (!is_array($result)) {
            $result = [$result, $result];
        }
        if (!is_array($interval)) {
            $result = [$interval, $interval];
        }

        $result[0] += $interval[0] - $this->deliveryMinInterval[0];
        $result[1] += $interval[1] - $this->deliveryMinInterval[1];

        return $result[0] === $result[1] ? $result[0] : $result;
    }

    public function getDeliveryInterval($bind = true)
    {
        $result = false;
        if ($date = $this->getIntervalFromModule()) {
            $result = $this->updateResult($result, $date);
        }

        $date = $this->getIntervalByRule($bind);
        if ($date !== false) {
            $result = $this->updateResult($result, $date);
        } elseif ($date = $this->getIntervalByBrand()) {
            $result = $this->updateResult($result, $date);
        }

        return $result;
    }

    private function bindInterval($days)
    {
        if (is_array($this->deliveryMinInterval)) {
            return [
                $this->deliveryMinInterval[0] + $days,
                $this->deliveryMinInterval[1] + $days,
            ];
        } elseif ((int)$this->deliveryMinInterval) {
            return $this->deliveryMinInterval + $days;
        } else {
            return $days;
        }
    }

    protected function getDeliveryTresholdRule()
    {
        $commonTreshold = [
            [
                'brands' => self::MI_BRANDS,
                'treshold' => [
                    1 => ['h' => 15, 'm' => 00],
                    2 => ['h' => 15, 'm' => 00],
                    3 => ['h' => 15, 'm' => 00],
                    4 => ['h' => 15, 'm' => 00],
                    5 => ['h' => 15, 'm' => 00],
                ],
            ],
            [
                'brands' => ['interskol'],
                'treshold' => [
                    1 => ['h' => 13, 'm' => 30],
                    2 => ['h' => 13, 'm' => 30],
                    3 => ['h' => 13, 'm' => 30],
                    4 => ['h' => 13, 'm' => 30],
                    5 => ['h' => 13, 'm' => 30],
                ],
            ],
            [
                'brands' => ['dewalt', 'stanley'],
                'treshold' => [
                    1 => ['h' => 11, 'm' => 00],
                    2 => ['h' => 11, 'm' => 00],
                    3 => ['h' => 11, 'm' => 00],
                    4 => ['h' => 11, 'm' => 00],
                    5 => ['h' => 11, 'm' => 00],
                ],
            ],
            [
                'brands' => [
                    'hammer',
                    'wester',
                    'military',
                    'hammer',
                    'military',
                    'karher',
                    'jonnesway',
                    'jonesway',
                    'ombra',
                    'thorvik'
                ],
                'treshold' => [
                    1 => ['h' => 11, 'm' => 00],
                    2 => ['h' => 11, 'm' => 00],
                    3 => ['h' => 11, 'm' => 00],
                    4 => ['h' => 11, 'm' => 00],
                    5 => ['h' => 11, 'm' => 00],
                ],
            ],
            [
                'brands' => ['metabo'],
                'treshold' => [
                    //1 => ['h' => 13, 'm' => 00],
                    //2 => ['h' => 13, 'm' => 00],
                    //3 => ['h' => 13, 'm' => 00],
                    //4 => ['h' => 13, 'm' => 00],
                    //5 => ['h' => 13, 'm' => 00],
                    1 => ['h' => 11, 'm' => 00],
                    2 => ['h' => 11, 'm' => 00],
                    3 => ['h' => 11, 'm' => 00],
                    4 => ['h' => 11, 'm' => 00],
                    5 => ['h' => 11, 'm' => 00],
                ],
            ],
            [
                'brands' => ['patriot', 'maxcut'],
                'treshold' => [
                    1 => ['h' => 14, 'm' => 30],
                    2 => ['h' => 14, 'm' => 30],
                    3 => ['h' => 14, 'm' => 30],
                    4 => ['h' => 14, 'm' => 30],
                    5 => ['h' => 14, 'm' => 30],
                ],
            ],
            [
                'brands' => [
                    'irsap',
                    'axis',
                    'grundfos',
                    'thermotrust',
                    'evan',
                    'viessmann',
                    'bugatti',
                    'reko',
                    'hajdu',
                    'stiebel_eltron',
                ],
                'treshold' => [
                    1 => ['h' => 14, 'm' => 30],
                    2 => ['h' => 14, 'm' => 30],
                    3 => ['h' => 14, 'm' => 30],
                    4 => ['h' => 14, 'm' => 30],
                    5 => ['h' => 14, 'm' => 30],
                ],
            ],
            [
                'brands' => [
                    'kalibr',
                    'makita',
                    'elitech',
                    'wert',
                    'dzhileks',
                    'champion',
                    'carver',
                    'rezer',
                    'hozelok',
                    'stiga',
                    'parma',
                    'fubag',
                    'condtrol',
                    'bort',
                    'vikhr',
                    'huter',
                    'stm',
                    'fiskars',
                    'ada',
                    'weber',
                    'gardena',
                ],
                'treshold' => [
                    1 => ['h' => 15, 'm' => 00],
                    2 => ['h' => 15, 'm' => 00],
                    3 => ['h' => 15, 'm' => 00],
                    4 => ['h' => 15, 'm' => 00],
                    5 => ['h' => 15, 'm' => 00],
                ],
            ],
            [
                'brands' => [
                    'bosch',
                    'stout',
                    'rosturplast',
                    'tera',
                    'rifar',
                ],
                'treshold' => [
                    1 => ['h' => 13, 'm' => 00],
                    2 => ['h' => 13, 'm' => 00],
                    3 => ['h' => 13, 'm' => 00],
                    4 => ['h' => 13, 'm' => 00],
                    5 => ['h' => 13, 'm' => 00],
                ],
            ],
            [
                'brands' => [
                    'zanussi',
                    'ballu',
                    'electrolux',
                ],
                'treshold' => [
                    1 => ['h' => 11, 'm' => 00],
                    2 => ['h' => 11, 'm' => 00],
                    3 => ['h' => 11, 'm' => 00],
                    4 => ['h' => 11, 'm' => 00],
                    5 => ['h' => 11, 'm' => 00],
                ],
            ],
            [
                'brands' => [
                    'kraft',
                ],
                'treshold' => [
                    1 => ['h' => 14, 'm' => 00],
                    2 => ['h' => 14, 'm' => 00],
                    3 => ['h' => 14, 'm' => 00],
                    4 => ['h' => 14, 'm' => 00],
                    5 => ['h' => 14, 'm' => 00],
                ],
            ],
            [
                'brands' => [
                    'zubr',
                    'stayer',
                    'grinda',
                    'kraftool',
                    'uragan',
                    'olfa',
                    'mirax',
                    'sibin',
                    'dexx',
                    'svetozar',
                    'legioner',
                    'mitutoyo',
                    'rapid',
                    'raco',
                    'rostok',
                    'keter',
                    'tevton',
                ],
                'treshold' => [
                    1 => ['h' => 13, 'm' => 00],
                    2 => ['h' => 13, 'm' => 00],
                    3 => ['h' => 13, 'm' => 00],
                    4 => ['h' => 13, 'm' => 00],
                    5 => ['h' => 13, 'm' => 00],
                ],
            ],

        ];


        return [
            self::DELIVERY_TYPE_SELF_PVZ => array_merge(
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_SELF_PVZ_YASNOGORSKAYA => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 20, 'm' => 00],
                            2 => ['h' => 20, 'm' => 00],
                            3 => ['h' => 20, 'm' => 00],
                            4 => ['h' => 20, 'm' => 00],
                            5 => ['h' => 20, 'm' => 00],
                            6 => null,
                            7 => ['h' => 17, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_SELF_PVZ_VIDNOYE => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 20, 'm' => 00],
                            2 => ['h' => 20, 'm' => 00],
                            3 => ['h' => 20, 'm' => 00],
                            4 => ['h' => 20, 'm' => 00],
                            5 => ['h' => 20, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_COURIER => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 17, 'm' => 00],
                            2 => ['h' => 17, 'm' => 00],
                            3 => ['h' => 17, 'm' => 00],
                            4 => ['h' => 17, 'm' => 00],
                            5 => ['h' => 17, 'm' => 00],
                            6 => ['h' => 17, 'm' => 00],
                            7 => ['h' => 17, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_DPD => array_merge(
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_BERU => array_merge(
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 14, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_DALLI => array_merge(
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 16, 'm' => 00],
                            2 => ['h' => 16, 'm' => 00],
                            3 => ['h' => 16, 'm' => 00],
                            4 => ['h' => 16, 'm' => 00],
                            5 => ['h' => 16, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_CDEK => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 17, 'm' => 00],
                            2 => ['h' => 17, 'm' => 00],
                            3 => ['h' => 17, 'm' => 00],
                            4 => ['h' => 17, 'm' => 00],
                            5 => ['h' => 17, 'm' => 00],
                            6 => ['h' => 17, 'm' => 00],
                            7 => ['h' => 17, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 16, 'm' => 00],
                            2 => ['h' => 16, 'm' => 00],
                            3 => ['h' => 16, 'm' => 00],
                            4 => ['h' => 16, 'm' => 00],
                            5 => ['h' => 16, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_CDEK_REGION => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 15, 'm' => 00],
                            2 => ['h' => 15, 'm' => 00],
                            3 => ['h' => 15, 'm' => 00],
                            4 => ['h' => 15, 'm' => 00],
                            5 => ['h' => 15, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 16, 'm' => 00],
                            2 => ['h' => 16, 'm' => 00],
                            3 => ['h' => 16, 'm' => 00],
                            4 => ['h' => 16, 'm' => 00],
                            5 => ['h' => 16, 'm' => 00],
                        ],
                    ],
                ]
            ),
            self::DELIVERY_TYPE_CDEK_EKB => array_merge(
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'treshold' => [
                            1 => ['h' => 13, 'm' => 00],
                            2 => ['h' => 13, 'm' => 00],
                            3 => ['h' => 13, 'm' => 00],
                            4 => ['h' => 13, 'm' => 00],
                            5 => ['h' => 13, 'm' => 00],
                        ],
                    ]
                ],
                $commonTreshold,
                [
                    [
                        'brands' => 'else',
                        'treshold' => [
                            1 => ['h' => 16, 'm' => 00],
                            2 => ['h' => 16, 'm' => 00],
                            3 => ['h' => 16, 'm' => 00],
                            4 => ['h' => 16, 'm' => 00],
                            5 => ['h' => 16, 'm' => 00],
                        ],
                    ],
                ]
            )
        ];
    }

    protected function getDeliveryRule()
    {
        $commonDeliveryRule = [
            [
                'brands' => ['dewalt', 'stanley'],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 1,
                    4 => 1,
                    5 => 3,
                    6 => 3,
                    7 => 2
                ]
            ],
            [
                'brands' => [
                    'bosch',
                    'dremel',
                ],
                'interval' => [
                    1 => 16,
                    2 => 22,
                    3 => 21,
                    4 => 20,
                    5 => 19,
                    6 => 18,
                    7 => 17
                ]
            ],
            [
                'brands' => [
                    //'bosch',
                    'stout',
                    'rosturplast',
                    //'dremel',
                    'finland',
                    'kapro',
                    'truper',
                    'wolfcraft',
                    'tsentroinstrument',
                    'tera',
                    'rifar'
                ],
                'interval' => [
                    1 => 2,
                    2 => 2,
                    3 => 2,
                    4 => 4,
                    5 => 4,
                    6 => 4,
                    7 => 3
                ]
            ],
            [
                'brands' => [
                    'zanussi',
                    'ballu',
                    'electrolux',
                ],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 1,
                    4 => 1,
                    5 => 3,
                    6 => 3,
                    7 => 2
                ]
            ],
            [
                'brands' => [
                    'irsap',
                    'axis',
                    'grundfos',
                    'thermotrust',
                    'evan',
                    'viessmann',
                    'bugatti',
                    'reko',
                    'hajdu',
                    'stiebel_eltron',
                ],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 5,
                    4 => 4,
                    5 => 3,
                    6 => 3,
                    7 => 2
                ]
            ],
            [
                'brands' => [
                    'metabo',
                    'jonnesway',
                    'jonesway',
                    'ombra',
                    'thorvik'
                ],
                'interval' => [
                    1 => 15,
                    2 => 14,
                    3 => 13,
                    4 => 12,
                    5 => 18,
                    6 => 17,
                    7 => 16
                ]
            ],
            [
                'brands' => [
                    'kalibr',
                    'hammer',
                    'wester',
                    'military',
                    'hammer',
                    'military',
                    'karher',
                    'makita',
                    'elitech',
                    'wert',
                    'dzhileks',
                    'fubag',
                    'condtrol',
                    'bort',
                    'vikhr',
                    'huter',
                    'stm',
                    'fiskars',
                    'ada',
                ],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 1,
                    4 => 1,
                    5 => 3,
                    6 => 3,
                    7 => 2
                ]
            ],
            [
                'brands' => [
                    'zubr',
                    'stayer',
                    'grinda',
                    'kraftool',
                    'uragan',
                    'olfa',
                    'mirax',
                    'sibin',
                    'dexx',
                    'svetozar',
                    'legioner',
                    'mitutoyo',
                    'rapid',
                    'raco',
                    'rostok',
                    'keter',
                    'tevton',
                ],
                'interval' => [
                    1 => 15,
                    2 => 14,
                    3 => 13,
                    4 => 12,
                    5 => 11,
                    6 => 10,
                    7 => 9
                ]
            ],
            [
                'brands' => [
                    'champion',
                    'carver',
                    'rezer',
                    'hozelok',
                    'stiga',
                    'parma',
                    'weber',
                    'gardena',
                ],
                'interval' => [
                    //1 => 4,
                    //2 => 3,
                    //3 => 2,
                    //4 => 8,
                    //5 => 7,
                    //6 => 6,
                    //7 => 5
                    1 => 18,
                    2 => 17,
                    3 => 16,
                    4 => 15,
                    5 => 21,
                    6 => 20,
                    7 => 19
                ]
            ],
            [
                'brands' => [
                    'patriot',
                    'maxcut',
                ],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 1,
                    4 => 1,
                    5 => 3,
                    6 => 3,
                    7 => 2
                ]
            ],
            [
                'brands' => ['jcb'],
                'interval' => [
                    1 => 2,
                    2 => 2,
                    3 => 2,
                    4 => 4,
                    5 => 4,
                    6 => 4,
                    7 => 3
                ]
            ],
            [
                'brands' => ['lesenka'],
                'interval' => [
                    1 => 5,
                    2 => 7,
                    3 => 6,
                    4 => 5,
                    5 => 5,
                    6 => 6,
                    7 => 5
                ]
            ],
            [
                'brands' => ['kraft'],
                'interval' => [
                    1 => 1,
                    2 => 1,
                    3 => 1,
                    4 => 1,
                    5 => 1,
                    6 => 3,
                    7 => 2
                ]
            ],
        ];


        $companyDeliveryRule = array_merge(
            $commonDeliveryRule,
            [
                [
                    'brands' => self::MI_BRANDS,
                    'interval' => [
                        1 => 0,
                        2 => 0,
                        3 => 0,
                        4 => 0,
                        5 => 0,
                        6 => 2,
                        7 => 1
                    ]
                ],
                /*
                [
                    'brands' => ['jonnesway', 'jonesway', 'ombra', 'thorvik'],
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 3,
                        6 => 3,
                        7 => 2
                    ]
                ],
                */
                [
                    'brands' => 'else',
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 3,
                        6 => 2,
                        7 => 1
                    ]
                ],
            ]
        );

        $finalDeliveryRule = [
            self::DELIVERY_TYPE_SELF_PVZ => [

                [
                    'brands' => ['dewalt', 'stanley'],
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 4,
                        6 => 3,
                        7 => 2
                    ]
                ],
                [
                    'brands' => [
                        'bosch',
                        'dremel',
                    ],
                    'interval' => [
                        1 => 16,
                        2 => 15,
                        3 => 14,
                        4 => 13,
                        5 => 19,
                        6 => 18,
                        7 => 17
                    ]
                ],
                [
                    'brands' => [
                        //'bosch',
                        'stout',
                        'rosturplast',
                        //'dremel',
                        'finland',
                        'kapro',
                        'truper',
                        'wolfcraft',
                        'tsentroinstrument',
                        'tera',
                        'rifar',
                    ],
                    'interval' => [
                        1 => 2,
                        2 => 2,
                        3 => 2,
                        4 => 4,
                        5 => 4,
                        6 => 4,
                        7 => 3
                    ]
                ],
                [
                    'brands' => [
                        'zanussi',
                        'ballu',
                        'electrolux',
                    ],
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 1,
                        6 => 3,
                        7 => 2
                    ]
                ],
                [
                    'brands' => [
                        'kalibr',
                        'hammer',
                        'wester',
                        'military',
                        'hammer',
                        'military',
                        'karher',
                        'metabo',
                        'makita',
                        'elitech',
                        'wert',
                        'dzhileks',
                        'fubag',
                        'condtrol',
                        'bort',
                        'vikhr',
                        'huter',
                        'stm',
                        'fiskars',
                        'ada'
                    ],
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 4,
                        6 => 3,
                        7 => 2
                    ]
                ],
                [
                    'brands' => [
                        'champion',
                        'carver',
                        'rezer',
                        'hozelok',
                        'stiga',
                        'parma',
                        'weber',
                        'gardena',
                    ],
                    'interval' => [
                        1 => 4,
                        2 => 3,
                        3 => 2,
                        4 => 8,
                        5 => 7,
                        6 => 6,
                        7 => 5
                    ]
                ],
                [
                    'brands' => [
                        'patriot',
                        'maxcut',
                    ],
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 3,
                        6 => 3,
                        7 => 2
                    ]
                ],
                [
                    'brands' => ['jcb'],
                    'interval' => [
                        1 => 2,
                        2 => 2,
                        3 => 2,
                        4 => 4,
                        5 => 4,
                        6 => 4,
                        7 => 3
                    ]
                ],
                [
                    'brands' => ['lesenka'],
                    'interval' => [
                        1 => 5,
                        2 => 7,
                        3 => 6,
                        4 => 5,
                        5 => 5,
                        6 => 6,
                        7 => 5
                    ]
                ],
                [
                    'brands' => self::MI_BRANDS,
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 1,
                        6 => 3,
                        7 => 2
                    ]
                ],
                [
                    'brands' => 'else',
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 4,
                        6 => 3,
                        7 => 2
                    ]
                ],
            ],
            self::DELIVERY_TYPE_COURIER => array_merge(
                [
                    [
                        'brands' => ['kraft'],
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 3,
                            7 => 2
                        ]
                    ]
                ],
                $commonDeliveryRule,
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 1,
                            6 => 1,
                            7 => 1
                        ]
                    ],
                    [
                        'brands' => 'else',
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 3,
                            7 => 2
                        ]
                    ],
                ]
            ),
            self::DELIVERY_TYPE_DPD => $companyDeliveryRule,
            self::DELIVERY_TYPE_DALLI => $companyDeliveryRule,
            self::DELIVERY_TYPE_CDEK => array_merge(
                [
                    [
                        'brands' => [
                            //'bosch',
                            'stout',
                            'rosturplast',
                            //'dremel',
                            'finland',
                            'kapro',
                            'truper',
                            'wolfcraft',
                            'tsentroinstrument',
                            'tera',
                            'rifar'
                        ],
                        'interval' => [
                            1 => 2,
                            2 => 2,
                            3 => 2,
                            4 => 2,
                            5 => 4,
                            6 => 4,
                            7 => 3
                        ]
                    ],
                ],
                $commonDeliveryRule,
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'interval' => [
                            1 => 0,
                            2 => 0,
                            3 => 0,
                            4 => 0,
                            5 => 0,
                            6 => 0,
                            7 => 0
                        ]
                    ],
                    /*
                    [
                        'brands' => ['jonnesway', 'jonesway', 'ombra', 'thorvik'],
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 3,
                            7 => 2
                        ]
                    ],
                    */
                    [
                        'brands' => 'else',
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 2,
                            7 => 1
                        ]
                    ],
                ]
            ),
            self::DELIVERY_TYPE_CDEK_REGION => array_merge(
                $commonDeliveryRule,
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'interval' => [
                            1 => 0,
                            2 => 0,
                            3 => 0,
                            4 => 0,
                            5 => 0,
                            6 => 2,
                            7 => 1
                        ]
                    ],
                    /*
                    [
                        'brands' => ['jonnesway', 'jonesway', 'ombra', 'thorvik'],
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 3,
                            7 => 2
                        ]
                    ],
                    */
                    [
                        'brands' => 'else',
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 2,
                            7 => 1
                        ]
                    ],
                ]
            ),
            self::DELIVERY_TYPE_CDEK_EKB => array_merge(
                $commonDeliveryRule,
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'interval' => [
                            1 => 0,
                            2 => 0,
                            3 => 0,
                            4 => 0,
                            5 => 0,
                            6 => 2,
                            7 => 1
                        ]
                    ],
                    /*
                    [
                        'brands' => ['jonnesway', 'jonesway', 'ombra', 'thorvik'],
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 3,
                            7 => 2
                        ]
                    ],
                    */
                    [
                        'brands' => 'else',
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 2,
                            7 => 1
                        ]
                    ],
                ]
            ),
            self::DELIVERY_TYPE_BERU => array_merge(
                $commonDeliveryRule,
                [
                    [
                        'brands' => self::MI_BRANDS,
                        'interval' => [
                            1 => 1,
                            2 => 1,
                            3 => 1,
                            4 => 1,
                            5 => 3,
                            6 => 2,
                            7 => 2
                        ]
                    ],
                    [
                        'brands' => 'else',
                        'interval' => [
                            1 => 2,
                            2 => 2,
                            3 => 2,
                            4 => 2,
                            5 => 3,
                            6 => 2,
                            7 => 2,
                        ]
                    ],
                ]
            ),
        ];

        $finalDeliveryRule[self::DELIVERY_TYPE_SELF_PVZ_YASNOGORSKAYA] = array_merge(
            [
                [
                    'brands' => self::MI_BRANDS,
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 1,
                        6 => 2,
                        7 => 1
                    ]
                ],
            ],
            $finalDeliveryRule[self::DELIVERY_TYPE_SELF_PVZ]
        );
        $finalDeliveryRule[self::DELIVERY_TYPE_SELF_PVZ_VIDNOYE] = array_merge(
            [
                [
                    'brands' => self::MI_BRANDS,
                    'interval' => [
                        1 => 1,
                        2 => 1,
                        3 => 1,
                        4 => 1,
                        5 => 1,
                        6 => 3,
                        7 => 2
                    ]
                ],
            ],
            $finalDeliveryRule[self::DELIVERY_TYPE_SELF_PVZ]
        );

        return $finalDeliveryRule;
    }

    public function getIntervalByRule($bind = true)
    {
        // Определить "текущий" день с учетом отсечки по брендам
        $today = time();

        $todayDow = (int)date('N', $today);
        $todayH = (int)date('H', $today);
        $todayM = (int)date('i', $today);
        $intervalDelta = 0;

        $tresholdRule = $this->getDeliveryTresholdRule();
        $deliveryRule = $this->getDeliveryRule();

        if (empty($tresholdRule[$this->deliveryType])) {
            return false;
        }

        foreach ($tresholdRule[$this->deliveryType] as $rule) {
            if (!is_array($rule['brands']) || $this->productHasBrand($rule['brands'])) {
                if (isset($rule['treshold'][$todayDow])) {
                    if (
                        $todayH > $rule['treshold'][$todayDow]['h'] ||
                        ($todayH == $rule['treshold'][$todayDow]['h'] && $todayM > $rule['treshold'][$todayDow]['m'])
                    ) {
                        ++$todayDow;
                        if ($todayDow > 7) {
                            $todayDow = 1;
                        }
                        $intervalDelta = 1;
                    }
                }
                break;
            }
        }

        // Определить срок доставки с учетом текущего дня
        if (empty($deliveryRule[$this->deliveryType])) {
            return false;
        }

        $interval = 0;

        foreach ($deliveryRule[$this->deliveryType] as &$rule) {
            if (!is_array($rule['brands']) || $this->productHasBrand($rule['brands'])) {
                if (isset($rule['interval'][$todayDow])) {
                    $interval = max($interval, $rule['interval'][$todayDow]);
                }
                break;
            }
        }
        $interval += $intervalDelta;

        return $bind
            ? (($interval <= 1) ? false : $this->bindInterval($interval - 1))
            : $interval;
    }

    /**
     * @param array $productIds
     * @return array
     */
    protected function getProductsBrand($productIds)
    {
        $result = [];
        foreach ($productIds as $productId) {
            if (!array_key_exists($productId, self::$cachedProductBrands)) {

                $brand = null;

                if($this->isMainWarehouseDeduction() && $this->existsOnMainWarehouse($productId)) {
                    $brand = self::MI_BRANDS[0];
                } else {
                    $properties = CIBlockElement::GetByID($productId)->GetNextElement()->GetProperties(
                        false,
                        ['CODE' => 'BRAND_']
                    );
                    if (is_array($properties) && !empty($properties['BRAND_'])) {
                        $propertyValue = CIBlockElement::GetByID($properties['BRAND_']['VALUE'])->GetNextElement();
                        if ($propertyValue) {
                            $brand = $propertyValue->fields['CODE'];
                        }
                    }
                }

                self::$cachedProductBrands[$productId] = $brand;
            }
            $result[$productId] = self::$cachedProductBrands[$productId];
        }
        return $result;
    }

    /**
     * @param string[] $brands
     * @return bool
     */
    protected function productHasBrand($brands)
    {
        $intersection = array_intersect($this->getProductsBrand($this->productId), $brands);
        return count($intersection) > 0;
    }

    private function getIntervalByBrand()
    {
        if (!$this->productId) {
            return false;
        }

        $conditions = [
            /*
            [
                'interval' => 1,
                'brands' => ['bosch']
            ]
            */
        ];

        foreach ($conditions as &$condition) {
            if ($this->productHasBrand($condition['brands'])) {
                return $this->bindInterval($condition['interval']);
            }
        }

        return false;
    }

    private function getIntervalFromModule()
    {
        $moduleName = 'local.main';
        if (Option::get($moduleName, 'use_delivery_calc', 'N') === 'N') {
            return false;
        }

        $dateFrom = strtotime(Option::get($moduleName, 'delivery_start_date', false));
        if (!$dateFrom || time() < $dateFrom) {
            return false;
        }

        $dateTo = Option::get($moduleName, 'target_start_date', false);
        if (!$dateTo || time() > strtotime($dateTo)) {
            return false;
        }

        $deliveryDate = new DateTime($dateTo);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($deliveryDate);

        return $this->bindInterval($interval->days + 1);
    }

    private function getDefaultInterval()
    {
        if ($this->defaultInterval) {
            return $this->defaultInterval;
        } else {
            if ($this->useFallbackInterval) {
                return 'Неизвестно';
            }
        }
    }

}