<?php
namespace SPF;

class Queue
{
    public $server;

    public function __construct($config, $server_type)
    {
        $this->queue = new $server_type($config);
    }

    public function push($data)
    {
        return $this->queue->push($data);
    }

    public function pop()
    {
        return $this->queue->pop();
    }

    public function __call($method, $param=array())
    {
        return call_user_func_array(array($this->queue, $method), $param);
    }
}
