<?php

namespace Instrum\Main\Exchange\Helpers;


use Phalcon\Mvc\Model\ResultsetInterface;

class ArrayHelper
{
    /**
     * Checks if array is associative
     *
     * @param array $collection
     * @return bool
     */
    final public static function isAssociative($collection)
    {
        return count(array_filter(array_keys($collection), 'is_string')) > 0;
    }

    /**
     * Indexes an array
     *
     * @param array $collection
     * @param $method
     * @return array
     */
    final public static function index($collection, $method)
    {
        $filtered = [];
        foreach ($collection as $element) {
            $key = self::getValue($element, $method);
            if($key !== null) {
                $filtered[$key] = $element;
            }
        }

        return $filtered;
    }

    /**
     * Helper method to get an array/object element or a default
     * @param $collection
     * @param $index
     * @param null $default
     * @return mixed|null
     */
    final public static function getValue($collection, $index, $default = null)
    {
        if(function_exists($index) || !is_string($index) && is_callable($index)) {
            return call_user_func($index, $collection);
        } elseif (is_object($collection)) {
            return $collection->$index;
        } elseif (is_array($collection)) {
            return (isset($collection[$index]) || array_key_exists($index, $collection))
                ? $collection[$index]
                : $default;
        }

        return $default;
    }

    /**
     * Sorts collection with hierarchy, possible for inserting into database
     *
     * @param array $collection
     * @param string $fieldId
     * @param string $fieldParentId
     * @return array
     */
    public static function sortHierarchy($collection, $fieldId, $fieldParentId)
    {
        if(!self::isAssociative($collection)) {
            $collection = self::index($collection, $fieldId);
        }

        $tree = [];

        $each = function ($item) use ($collection, &$tree, &$each, $fieldId, $fieldParentId) {
            $parentId = self::getValue($item, $fieldParentId) ?: 0;

            if (!isset($tree[$parentId])) {
                if (!empty($collection[$parentId])) {
                    $each($collection[$parentId]);
                }
                $tree[$parentId] = 0;
            }
            $tree[self::getValue($item, $fieldId)] = $item;
        };

        foreach ($collection as $item) {
            $each($item);
        }

        $tree = array_filter($tree);
        return $tree;
    }

    /**
     * Retrieves all of the values for a given key
     *
     * @param array $collection
     * @param string $element
     * @return array
     */
    final public static function pluck($collection, $element)
    {
        $filtered = [];
        foreach ($collection as $item) {
            $filtered[] = self::getValue($item, $element);
        }
        return $filtered;
    }

    /**
     * Maps one item value to another for each collection elements
     *
     * @param array $collection
     * @param string $from
     * @param string|null $to
     * @param string|null $group
     * @return array
     */
    final public static function map($collection, $from, $to, $group = null)
    {
        $filtered = [];
        foreach ($collection as $element) {
            $key = static::getValue($element, $from);
            $value = static::getValue($element, $to);
            if ($group !== null) {
                $filtered[static::getValue($element, $group)][$key] = $value;
            } else {
                $filtered[$key] = $value;
            }
        }
        return $filtered;
    }
}