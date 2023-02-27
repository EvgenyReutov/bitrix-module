<?php


namespace Instrum\Main\Listing;


class SortHelper
{
    const SORT_ASC = 'ASC';
    const SORT_DESC = 'DESC';

    const DEFAULT_SORT_RULE_NAME = 'default';

    protected static function getAvailableSortField()
    {
        $sortField = 'CATALOG_AVAILABLE';
        if (!empty($_SESSION['SOTBIT_REGIONS']['STORE'])) {
            $sortField = 'PROPERTY_REGION_AVAILABLE_' . $_SESSION['SOTBIT_REGIONS']['ID'];
        }
        return [
            'field' => $sortField,
            'order' => self::SORT_DESC,
        ];
    }

    public static function getSortRules($withDiscounts = false, $withRelevance = false)
    {
        $result = [
            self::DEFAULT_SORT_RULE_NAME => [
                'title' => 'Рекомендуем',
                'sort' => 'default',
                'direction' => self::SORT_ASC
            ],
            'price_asc' => [
                'title' => 'По возрастанию цены',
                'sort' => 'price',
                'direction' => self::SORT_ASC
            ],
            'price_desc' => [
                'title' => 'По убыванию цены',
                'sort' => 'price',
                'direction' => self::SORT_DESC
            ],
        ];

        if ($withDiscounts) {
            $result['discount'] = [
                'title' => 'По размеру скидки',
                'sort' => 'discount',
                'direction' => self::SORT_ASC
            ];
        }

        if ($withRelevance) {
            $result[self::DEFAULT_SORT_RULE_NAME] = [
                'title' => 'Рекомендуем',
                'sort' => 'relevance',
                'direction' => self::SORT_ASC
            ];
        }

        return $result;
    }

    public static function getSortDescription($withDiscounts = false, $withRelevance = false)
    {
        $availableSortField = static::getAvailableSortField();

        $result = [
            self::DEFAULT_SORT_RULE_NAME => [
                self::SORT_ASC => [
                    $availableSortField,
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'SORT', 'order' => self::SORT_ASC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'CATALOG_PRICE_' . SortPropertiesService::SELL_PRICE_ID, 'order' => self::SORT_ASC],
                    ['field' => 'ID', 'order' => self::SORT_ASC]
                ],
                self::SORT_DESC => [
                    $availableSortField,
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX .SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_ASC],
                    ['field' => 'SORT', 'order' => self::SORT_DESC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_ASC],
                    ['field' => 'CATALOG_PRICE_' . SortPropertiesService::SELL_PRICE_ID, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_DESC]
                ],
            ],
            'price' => [
                self::SORT_ASC => [
                    $availableSortField,
                    ['field' => 'CATALOG_PRICE_' . SortPropertiesService::SELL_PRICE_ID, 'order' => self::SORT_ASC],
                    ['field' => 'ID', 'order' => self::SORT_ASC]
                ],
                self::SORT_DESC => [
                    $availableSortField,
                    ['field' => 'CATALOG_PRICE_' . SortPropertiesService::SELL_PRICE_ID, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_DESC]
                ],
            ]
        ];

        if ($withDiscounts) {
            $result['discount'] = [
                self::SORT_ASC => [
                    $availableSortField,
                    ['field' => 'SORT', 'order' => self::SORT_ASC],
                    ['field' => 'PROPERTY_SALE_PERCENT', 'order' => self::SORT_DESC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_ASC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_ASC]
                ],
                self::SORT_DESC => [
                    $availableSortField,
                    ['field' => 'SORT', 'order' => self::SORT_DESC],
                    ['field' => 'PROPERTY_SALE_PERCENT', 'order' => self::SORT_ASC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_DESC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_DESC]
                ],
            ];
        }

        if ($withRelevance) {
            $result['relevance'] = [
                self::SORT_ASC => [
                    $availableSortField,
                    ['field' => 'rank', 'order' => self::SORT_ASC],
                    ['field' => 'SORT', 'order' => self::SORT_ASC],
                    ['field' => 'PROPERTY_SALE_PERCENT', 'order' => self::SORT_DESC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_ASC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_ASC]
                ],
                self::SORT_DESC => [
                    $availableSortField,
                    ['field' => 'rank', 'order' => self::SORT_DESC],
                    ['field' => 'SORT', 'order' => self::SORT_DESC],
                    ['field' => 'PROPERTY_SALE_PERCENT', 'order' => self::SORT_ASC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_STM_CODE, 'order' => self::SORT_DESC],
                    ['field' => SortPropertiesService::IBLOCK_FIELD_PREFIX . SortPropertiesService::PROPERTY_MARGIN_CODE, 'order' => self::SORT_DESC],
                    ['field' => 'ID', 'order' => self::SORT_DESC]
                ],
            ];
        }

        return $result;
    }

    public static function getSortRuleName($inputValue, $withDiscounts = false, $withRelevance = false)
    {
        if (empty($inputValue)) {
            $inputValue = self::DEFAULT_SORT_RULE_NAME;
        } else {
            $inputValue = mb_strtolower($inputValue);
        }

        $sortRules = self::getSortRules($withDiscounts, $withRelevance);
        if (!array_key_exists($inputValue, $sortRules)) {
            $inputValue = self::DEFAULT_SORT_RULE_NAME;
        }

        return $inputValue;
    }

    public static function getSortParameters($sortRuleName, $withDiscounts = false, $withRelevance = false)
    {
        $sortRuleName = self::getSortRuleName($sortRuleName, $withDiscounts, $withRelevance);
        $sortDescription = self::getSortDescription($withDiscounts, $withRelevance);
        $rule = self::getSortRules($withDiscounts, $withRelevance)[$sortRuleName];

        $ruleParams = $sortDescription[$rule['sort']][$rule['direction']];

        $result = [];
        foreach ($ruleParams as $index => $value) {
            $postfix = $index > 0 ? $index + 1 : '';
            $result['ELEMENT_SORT_FIELD' . $postfix] = $value['field'];
            $result['ELEMENT_SORT_ORDER' . $postfix] = $value['order'];
        }

        return $result;
    }
}