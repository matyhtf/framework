<?php

namespace SPF\Exception;

/**
 * 属性不被允许异常类
 */
class PropertyNotAllowedException extends LogicException
{
    public $class = null;
    public $property = null;

    public function __construct(string $class, string $property, string $message)
    {
        $this->class = $class;
        $this->property = $property;

        $error = "Property {$class}->{$property} {$message}";

        parent::__construct($error, 0);
    }

    public function getClass()
    {
        return $this->class;
    }

    public function getProperty()
    {
        return $this->property;
    }
}
