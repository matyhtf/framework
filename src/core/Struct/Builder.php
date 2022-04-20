<?php

namespace SPF\Struct;

use SPF\Validator\Validator;
use SPF\Struct\Map;

class Builder
{
    /**
     * Build struct object.
     * 
     * @param string $className
     * @param array $param
     * 
     * @return object
     */
    public static function builder(string $className, array $param)
    {
        $class = new $className;
        foreach($param as $name => $value) {
            if (property_exists($class, $name) || ($class instanceof Map)) {
                $class->{$name} = $value;
            }
        }

        return $class;
    }

    /**
     * Try transfer array to struct object if the param type is struct object.
     * 
     * @param string $class
     * @param string $method
     * @param array $params
     * 
     * @return array
     */
    public static function tryTransferArrayToStructObject($class, $method, $params)
    {
        $method = strtolower($method);

        $map = Validator::getValidateMap();
        if (!isset($map[$class]) || empty($map[$class][$method])) {
            return $params;
        }

        static::recursiveTransfer($map[$class][$method], $params);

        return $params;
    }

    /**
     * Recursive transfer.
     * 
     * @param array $fields
     * @param array|object $params
     */
    protected static function recursiveTransfer($fields, &$params)
    {
        foreach ($fields as $key => $value) {
            $type = $value['type'];
            if (is_array($params)) {
                if (!isset($params[$key])) {
                    continue;
                }
                if (class_exists($type) && is_array($params[$key])) {
                    $params[$key] = static::builder($type, $params[$key]);
                }
                if (!empty($value['extends'])) {
                    static::recursiveTransfer($value['extends'], $params[$key]);
                }
            } elseif (is_object($params)) {
                if (!property_exists($params, $key) && !($params instanceof Map)) {
                    continue;
                }
                if (class_exists($type) && is_array($params->{$key})) {
                    $params->{$key} = static::builder($type, $params->{$key});
                }
                if (!empty($value['extends'])) {
                    static::recursiveTransfer($value['extends'], $params->{$key});
                }
            } else {
                continue;
            }
        }
    }
}
