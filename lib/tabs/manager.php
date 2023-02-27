<?php

namespace Instrum\Main\Tabs;

class Manager{

    protected $tabs = [];
    protected $arResult = null;
    protected $arParams = null;
    protected $path;
    protected $application;

    public function __construct($path, $arResult = false, $arParams = false)
    {
        global $APPLICATION;
        $this->application = $APPLICATION;
        $this->path = $path;
        $this->arResult = $arResult;
        $this->arParams = $arParams;
    }

    public function addTab($code, Tab $tab)
    {
        $this->tabs[$code] = $tab;
        return $this;
    }

    public function makeAjaxTab()
    {
        /**
         * @var string $code
         * @var Tab $tab
         */
        foreach ($this->tabs as $code => $tab)
        {
            if(
                ($tabReset = $tab->getBufferReset()) &&
                (isset($_REQUEST[$tabReset]))
            )
            {
                $this->application->RestartBuffer();
                return $this->getTabContent($code, $tab);
            }
        }

        return false;
    }

    public function make()
    {
        return $this->makeTabsHtml();
    }

    protected function getNode($tn, $attrs, $content = '')
    {
        $arAttrs = [];
        foreach($attrs as $attrName => $attrValue)
        {
            $arAttrs[] = "${attrName}=\"${attrValue}\"";
        }

        return "<${tn}".($arAttrs ? ' '.implode(" ", $arAttrs) : '').">".$content."</${tn}>";
    }

    protected function makeTabsHtml()
    {
        $headers = [];
        $tabs = [];
        $meta = [];

        /**
         * @var string $code
         * @var Tab $tab
         */
        foreach($this->tabs as $code => $tab)
        {
            if($html = $this->getTabContent($code, $tab))
            {

                $li_attrs = ['role' => 'presentation'];
                if(!count($headers))
                    $li_attrs['class'] = 'active';

                $a_attrs = [
                    'href' => '#pp_'.$code,
                    'role' => 'tab',
                    'data-toggle' => 'tab'
                ];
                if($headerClass = $tab->getHeaderClass())
                    $a_attrs['class'] = $headerClass;

                $headers[] = $this->getNode(
                    'li',
                    $li_attrs,
                    $this->getNode(
                        'a',
                        $a_attrs,
                        $tab->getName().($tab->getAfterName() ? $tab->getAfterName() : ''))
                );

                $meta[] = ["NAME" => $tab->getName(), "CODE" => '#pp_'.$code];

                $tabs[] = $this->getNode('div', [
                    'role' => 'tabpanel',
                    'class' => 'tab-pane'.(count($tabs) ? '' : ' active'),
                    'id' => 'pp_'.$code
                ], $html);
            }
        }

        return [
            'HEADERS' => $headers,
            'META' => $meta,
            'TABS' => $tabs
        ];
    }

    private function getAjaxTab($code, Tab $arTab)
    {
        $this->application->RestartBuffer();
        $arTab->setIsAjax(true);
        $this->include($code, $arTab);
        die;
    }

    public function getTabContent($code, $arTab)
    {
        ob_start();
        $this->include($code, $arTab);
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private function include($code, $arTab)
    {
        $arResult = $this->arResult;
        $arParams = $this->arParams;

        include $this->path.'/'.$code.'.php';
    }

}