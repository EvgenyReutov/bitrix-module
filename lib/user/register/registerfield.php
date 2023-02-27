<?php

namespace Instrum\Main\User\Register;


class RegisterField{

    protected $id;

    protected $name;
    protected $field_name;
    protected $table_name;

    protected $type = 'text';
    protected $required = false;

    protected $class_modifier = false;
    protected $data = [];

    protected $children = [];
    protected $placeholder = null;

    protected $class_suffix;

    protected $container_id;
    protected $value;

    protected $nativeType;

    public function __construct($name, $table_name, $field_name)
    {
        $this->name = $name;
        $this->table_name = $table_name;
        $this->field_name = $field_name;
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
     * @return RegisterField
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        if(!$this->field_name)
            return $this->name;
        return $this->field_name;
    }

    /**
     * @param mixed $field_name
     * @return RegisterField
     */
    public function setFieldName($field_name)
    {
        $this->field_name = $field_name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->table_name;
    }

    /**
     * @param mixed $table_name
     * @return RegisterField
     */
    public function setTableName($table_name)
    {
        $this->table_name = $table_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @param bool $nativeType
     * @return RegisterField
     */
    public function setType($type, $nativeType = false)
    {
        $this->type = $type;
        if (!$nativeType)
            $nativeType = $type;

        $this->nativeType = $nativeType;
        return $this;
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @param bool $required
     * @return RegisterField
     */
    public function setRequired($required)
    {
        $this->required = $required;
        return $this;
    }

    /**
     * @return bool
     */
    public function isClassModifier()
    {
        return $this->class_modifier;
    }

    /**
     * @param bool $class_modifier
     * @return RegisterField
     */
    public function setClassModifier($class_modifier)
    {
        $this->class_modifier = $class_modifier;
        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array $data
     * @return RegisterField
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return RegisterField
     */
    public function appendData($key, $value)
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param array $children
     * @return RegisterField
     */
    public function setChildren($children)
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return string
     */
    public function getPlaceholder()
    {
        if(!$this->placeholder)
            return $this->field_name;

        return $this->placeholder;
    }

    /**
     * @param string $placeholder
     * @return RegisterField
     */
    public function setPlaceholder($placeholder)
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        if(!$this->id)
            return "rf_".$this->name;
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return RegisterField
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getContainerId()
    {
        return $this->container_id;
    }

    /**
     * @param mixed $container_id
     * @return RegisterField
     */
    public function setContainerId($container_id)
    {
        $this->container_id = $container_id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClassSuffix()
    {
        return $this->class_suffix;
    }

    /**
     * @param mixed $class_suffix
     * @return RegisterField
     */
    public function setClassSuffix($class_suffix)
    {
        $this->class_suffix = $class_suffix;
        return $this;
    }


    public function getInputType()
    {
        switch ($this->nativeType)
        {
            case 'picker':
                return 'tel';
            case 'confirm_password':
                return 'password';
            default:
                return $this->type;
        }
    }

    public function getClassValue()
    {
        $values = ["form-input"];

        switch($this->getType())
        {
            case 'email':
                $values[] = "form-email";
                break;
            case 'confirm_password':
                $values[] = "form-password-confirm";
                break;
            case 'checkbox':
                $values[] = "form-checkbox";
                break;
            case 'picker':
                $values[] = "form-phone-picker";
                break;
            case 'submit':
                $values[] = "form-submit";
        }

        if($this->isRequired())
        {
            $values[] = "form-required";
        }

        if($suffix = $this->getClassSuffix())
        {
            $values[] = $suffix;
        }

        return implode(" ", $values);
    }

    public function getDataValues()
    {
        $values = [];
        foreach($this->data as $key => $value)
        {
            $values[] = 'data-' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
        }

        if($values) return implode(" ", $values);
        return "";
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param mixed $value
     * @return RegisterField
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

}
