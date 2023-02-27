<?php

namespace Instrum\Main\Catalog\Sticker;

class Item{

    protected $class;
    protected $content;
    protected $tooltip;
    protected $wrap;

    public function __construct($class, $content, $tooltip = false, $wrap = 'span')
    {
        $this->class = $class;
        $this->content = $content;
        $this->tooltip = $tooltip;
        $this->wrap = $wrap;
    }

    public function getClassName()
    {
        return $this->class;
    }

    public function getContent()
    {
        $content = $this->content;
        if($this->wrap)
        {
            $content = $this->wrapContent($content);
        }

        return $content;
    }

    public function getTooltip()
    {
        return $this->tooltip;
    }

    protected function wrapContent($text)
    {
        $wrap = $this->wrap;
        return "<${wrap}>${text}</${wrap}>";
    }
}