<?php

namespace SPF\Client;

class CoURLResult
{
    protected $url;
    protected $curl;


    public $id;
    public $method;
    public $data;
    public $result;
    public $callback;
    public $error;
    public $info;

    public function __construct($url, $callback, $multiHandle)
    {
        $this->url = $url;
        $this->callback = $callback;
        $this->curl = new CURL();
        $this->curl->setMultiHandle($multiHandle);
        $this->id = intval($this->curl->getHandle());
    }

    public function execute()
    {
        if ($this->method == CoURL::METHOD_GET) {
            $this->curl->get($this->url);
        } elseif ($this->method == CoURL::METHOD_POST) {
            $this->curl->post($this->url, $this->data);
        }
    }
}
