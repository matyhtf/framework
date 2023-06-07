<?php

namespace SPF\Queue;

use SPF;

class HttpQueue implements SPF\IFace\Queue
{
    public $host = 'localhost';
    public $debug = false;
    public $port = 1218;
    public $client_type;
    public $http;
    public $name = 'swoole';
    public $charset = 'utf-8';

    private $base;
    private $server_url;

    public function __construct($config)
    {
        if (!empty($config['server_url'])) {
            $this->server_url = $config['server_url'];
        }
        if (!empty($config['name'])) {
            $this->name = $config['name'];
        }
        if (!empty($config['charset'])) {
            $this->charset = $config['charset'];
        }
        if (!empty($config['debug'])) {
            $this->debug = $config['debug'];
        }

        $this->base = "{$this->server_url}/?charset={$this->charset}&name={$this->name}";

        if (!extension_loaded('curl')) {
            $header[] = "Connection: keep-alive";
            $header[] = "Keep-Alive: 300";
            $this->client_type = 'curl';
            $this->http = new SPF\Client\CURL($this->debug);
            $this->http->addHeaders($header);
        } else {
            $this->client_type = 'SPF\Client\Http';
        }
    }

    protected function doGet($opt)
    {
        $url = $this->base . '&opt=' . $opt;
        if ($this->client_type == 'curl') {
            return $this->http->get($url);
        } else {
            return SPF\Client\Http::quickGet($url);
        }
    }

    protected function doPost($opt, $data)
    {
        $url = $this->base . '&opt=' . $opt;
        if ($this->client_type == 'curl') {
            return $this->http->post($url, $data);
        } else {
            return SPF\Client\Http::quickPost($url, $data);
        }
    }

    public function push($data)
    {
        $result = $this->doPost("put", $data);
        if ($result == "HTTPSQS_PUT_OK") {
            return true;
        } else {
            if ($result == "HTTPSQS_PUT_END") {
                return $result;
            } else {
                return false;
            }
        }
    }

    public function pop()
    {
        $result = $this->doGet("get");
        if ($result == false || $result == "HTTPSQS_ERROR" || $result == false) {
            return false;
        } else {
            parse_str($result, $res);
            return $res;
        }
    }

    public function status()
    {
        $result = $this->doGet("status");
        if ($result == false || $result == "HTTPSQS_ERROR" || $result == false) {
            return false;
        } else {
            return $result;
        }
    }
}
