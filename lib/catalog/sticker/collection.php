<?php

namespace Instrum\Main\Catalog\Sticker;

use Instrum\Main\Catalog\Stikers;

class Collection{

    protected $items = [];
    protected $custom_items = [];
    protected $priority;

    public function __construct($nodes, $exclude, $priority = false)
    {
        $this->priority = $priority;

        /** @var \Instrum\Main\Iblock\Element\Item $node */
        foreach ($nodes as $node)
        {
            if(
                ($baseClassName = $node['PROPERTIES']['INHERIT_BASE']['VALUE_XML_ID']) &&
                in_array($baseClassName, array_keys(Stikers::$inherited_classes))
            )
            {
                if(in_array($baseClassName, $exclude))
                    continue;

                $this->addNode(new Item($baseClassName, Stikers::$inherited_classes[$baseClassName]['LABEL']));
            }
            elseif (!empty($node['CODE'])) {
                $this->addCustomNode(new Item($node['CODE'], $node['PROPERTIES']['STYLE']['~VALUE']['TEXT'], $node['PROPERTIES']['TOOLTIP']['~VALUE']['TEXT'], false ));
            }
            elseif ($node['PROPERTIES']['TOOLTIP']['~VALUE']['TEXT']) {
                $this->addCustomNode(new Item('stock', $node['PROPERTIES']['STYLE']['~VALUE']['TEXT'], $node['PROPERTIES']['TOOLTIP']['~VALUE']['TEXT'], false));
            }
            else
            {
                $this->addCustomNode(new Item('custom', $node['PROPERTIES']['STYLE']['~VALUE']['TEXT'], false, false));
            }
        }
    }

    public function addNode(Item $item)
    {
        $this->items[$item->getClassName()] = $item;
    }

    public function addCustomNode($item)
    {
        $this->custom_items[] = $item;
    }

    public function getNodes()
    {
        if($this->priority)
        {
            foreach($this->priority as $className)
            {
                if(in_array($className, array_keys($this->items)))
                {
                    yield $this->items[$className];
                }
            }
        }
        else
        {
            foreach($this->items as $item)
            {
                yield $item;
            }
        }

        foreach($this->custom_items as $item)
        {
            yield $item;
        }
    }

    public function size()
    {
        return count($this->items) + count($this->custom_items);
    }

    public function sizeToolTipStickers()
    {
        $result = 0;
        foreach ($this->custom_items as $item) {
            if ($item->getClassName() == 'stock')
                $result++;
        }
        return $result;
    }
}