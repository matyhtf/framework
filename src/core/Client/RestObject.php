<?php

namespace SPF\Client;

class RestObject
{
    public $server_url;
    private $class;
    private $rest;
    public $attrs;

    function __construct($class, $rest)
    {
        $this->class = $class;
        $this->rest = $rest;
    }

    function __get($attr)
    {
        return $this->attrs[$attr];
    }

    function __set($attr, $value)
    {
        $this->attrs[$attr] = $value;
        return true;
    }

    function __call($method, $param)
    {
        return $this->rest->method($this->class, $method, $this->attrs, $param);
    }
}