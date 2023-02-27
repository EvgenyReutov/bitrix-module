<?php

namespace Instrum\Main\Tabs;

class Tab{

    protected $name;
    protected $afterName;
    protected $headerClass;
    protected $bufferReset;
    protected $isAjax = false;

    public function __construct($tabName)
    {
        $this->name = $tabName;
    }

    /**
     * @return mixed
     */
    public function getAfterName()
    {
        return $this->afterName;
    }

    /**
     * @param mixed $afterName
     * @return Tab
     */
    public function setAfterName($afterName)
    {
        $this->afterName = $afterName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     * @return Tab
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHeaderClass()
    {
        return $this->headerClass;
    }

    /**
     * @param mixed $headerClass
     * @return Tab
     */
    public function setHeaderClass($headerClass)
    {
        $this->headerClass = $headerClass;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getBufferReset()
    {
        return $this->bufferReset;
    }

    /**
     * @param mixed $bufferReset
     * @return Tab
     */
    public function setBufferReset($bufferReset)
    {
        $this->bufferReset = $bufferReset;
        return $this;
    }

    /**
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->isAjax;
    }

    /**
     * @param bool $isAjax
     */
    public function setIsAjax(bool $isAjax)
    {
        $this->isAjax = $isAjax;
    }

}