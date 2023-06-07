<?php

namespace SPF\Client;

class RestObject
{
    public $server_url;
    private $class;
    private $rest;
    public $attrs;

    public function __construct($class, $rest)
    {
        $this->class = $class;
        $this->rest = $rest;
    }

    public function __get($attr)
    {
        return $this->attrs[$attr];
    }

    public function __set($attr, $value)
    {
        $this->attrs[$attr] = $value;
        return true;
    }

    public function __call($method, $param)
    {
        return $this->rest->method($this->class, $method, $this->attrs, $param);
    }
}
