<?php

namespace Instrum\Main\Iblock\Element;

/**
 * Коллекция элементов инфоблока
 * Class Collection
 * @package Instrum\Main\Iblock\Element
 */
class Collection
{
    protected $arFilter = [];
    protected $arSort = [];
    protected $arNavParams = false;
    protected $arSelect = [];

    protected $cache = [];

    protected $el;


    /**
     * Collection constructor.
     */
    public function __construct()
    {
        $this->el = new \CIBlockElement();
    }

    /**
     * @return array
     */
    public function getList()
    {
        $arResult = [];

        $cache_id = serialize([
            'SORT' => $this->arSort,
            'FILTER' => $this->arFilter,
        ]);

        if (!empty($this->cache[__FUNCTION__][$cache_id])) {
            return $this->cache[__FUNCTION__][$cache_id];
        } else {
            $rs = $this->getListResult();
            while ($item = $rs->GetNext()) {
                
                $arResult[] = new Item($item);
            }

            $this->cache[__FUNCTION__][$cache_id] = $arResult;
        }

        return $arResult;
    }

    /**
     * @return \CIBlockResult|int
     */
    private function getListResult()
    {
        return \CIBlockElement::GetList($this->arSort, $this->arFilter, false, $this->arNavParams, $this->arSelect);
    }

    /**
     * @return Item
     */
    public function get()
    {
        $cache_id = serialize([
            'SORT' => $this->arSort,
            'FILTER' => $this->arFilter,
        ]);

        if (!empty($this->cache[__FUNCTION__][$cache_id])) {
            $item = $this->cache[__FUNCTION__][$cache_id];
        } elseif ($item = $this->getListResult()->GetNext()) {
            $item = new Item($item);
            $this->cache[__FUNCTION__][$cache_id] = $item;
        }

        return $item;
    }

    /**
     * @param array $arFilter
     * @return Collection
     */
    public function setFilter(array $arFilter = [])
    {
        $this->arFilter = $arFilter;
        return $this;
    }

    /**
     * @param array $arSort
     * @return Collection
     */
    public function setSort(array $arSort = [])
    {
        $this->arSort = $arSort;
        return $this;
    }

    /**
     * @param bool $arNavParams
     * @return Collection
     */
    public function setNavParams($arNavParams)
    {
        $this->arNavParams = $arNavParams;
        return $this;
    }

    public function setSelect($arSelect){
        $this->arSelect = $arSelect;
        return $this;
    }
}
