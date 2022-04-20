<?php

namespace SPF\Struct;

use SPF\Exception\PropertyNotAllowedException;
use ReflectionClass;

class Map extends BaseStruct
{
    /**
     * Append properties.
     * 
     * @var array
     */
    protected $appendProps = [];

    /**
     * Set property`s value
     * 
     * @param string $key property name
     * @param mixed $value propety value
     * 
     * @return self
     */
    public function set(string $key, $value)
    {
        if (property_exists($this, $key)) {
            $this->{$key} = $value;
        } else {
            $this->appendProps[$key] = $value;
        }

        return $this;
    }

    /**
     * Batch set property`s values
     * 
     * @param array $values key-value map
     * 
     * @return self
     */
    public function sets(array $values)
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Get property value
     * 
     * @param string $key property name
     * @param mixed $default default value
     * 
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!property_exists($this, $key)) {
            if (!isset($this->appendProps[$key])) {
                throw new PropertyNotAllowedException(__CLASS__, $key, 'not exists');
            }

            $value = $this->{$key};
        } else {
            $value = $this->{$key};
        }


        return $value ?: $default;
    }

    /**
     * Batch get property values
     * 
     * @param array $keys property names
     * 
     * @return array
     */
    public function gets(array $keys)
    {
        $result = [];
        foreach($keys as $key) {
            $result[$key] = $this->get($key, null);
        }

        return $result;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $data = [];
        $refClass = new ReflectionClass($this);
        foreach($refClass->getProperties() as $refProp) {
            if ($refProp->isPublic()) {
                $prop = $refProp->getName();
                $data[$prop] = $this->{$prop};
            }
        }

        return array_merge($data, $this->appendProps);
    }

    public function __get($key)
    {
        if (!isset($this->appendProps[$key])) {
            throw new PropertyNotAllowedException(__CLASS__, $key, 'not exists');
        }

        return $this->appendProps[$key];
    }

    public function __set($key, $value)
    {
        $this->appendProps[$key] = $value;
    }
}
