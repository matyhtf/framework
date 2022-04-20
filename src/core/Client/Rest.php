<?php

namespace SPF\Client;

use SPF;
use SPF\Tool;

/**
 * SPF WebService客户端
 * @author Tianfeng.Han
 * @package SPF
 * @subpackage Client
 */
class Rest
{
    public $server_url;
    public $keep_alive = false;
    public $client_type;
    public $http;
    public $debug;

    /**
     * Rest constructor.
     * @param $url
     * @param string $user
     * @param string $password
     * @throws \Exception
     */
    function __construct($url, $user = '', $password = '')
    {
        $this->server_url = $url . "?user=$user&pass=" . SPF\Auth::makePasswordHash($user, $password) . '&';
        $this->client_type = 'curl';
        $this->http = new CURL($this->debug);
        if ($this->keep_alive) {
            $this->http->setHeader('Connection', 'keep-alive');
            $this->http->setHeader('Keep-Alive', 300);
        }
    }

    /**
     * @param $param
     * @param null $post
     * @return mixed
     */
    function call($param, $post = null)
    {
        foreach ($param as &$m) {
            if (is_array($m) or is_object($m)) $m = serialize($m);
        }
        $url = $this->server_url . Tool::combine_query($param);
        if ($post === null) $res = $this->http->get($url);
        else $res = $this->http->post($url, $post);
        if ($this->debug) echo $url, BL, $res;
        return json_decode($res);
    }

    function method($class, $method, $attrs, $param)
    {
        $attrs['class'] = $class;
        $attrs['method'] = $method;
        return $this->call($attrs, $param);
    }

    function func($func, $param)
    {
        $param['func'] = $func;
        return $this->call($param);
    }

    function create($class, $param = array())
    {
        $obj = new RestObject($class, $this);
        $obj->attrs = $param;
        return $obj;
    }
}

