<?php
namespace SPF;

class Queue
{
    public $server;

	function __construct($config, $server_type)
    {
    	$this->queue = new $server_type($config);
    }

    function push($data)
    {
    	return $this->queue->push($data);
    }

    function pop()
    {
    	return $this->queue->pop();
    }

    function __call($method, $param=array())
    {
    	return call_user_func_array(array($this->queue, $method), $param);
    }
}