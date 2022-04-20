<?php

namespace SPF\Struct;

use SPF\Exception\PropertyNotAllowedException;

abstract class Struct extends BaseStruct
{
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
        $this->{$key} = $value;

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
            // If the property not exists, the magic function __set will throw exception.
            $this->{$key} = $value;
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
            throw new PropertyNotAllowedException(__CLASS__, $key, 'not exists');
        }

        $value = $this->{$key};

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
        foreach ($keys as $key) {
            if (!property_exists($this, $key)) {
                throw new PropertyNotAllowedException(__CLASS__, $key, 'not exists');
            }

            $result[$key] = $this->{$key};
        }

        return $result;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return get_object_vars($this);
    }

    public function __set($key, $value)
    {
        throw new PropertyNotAllowedException(__CLASS__, $key, 'not allowed set if the class not exists the property');
    }
}
