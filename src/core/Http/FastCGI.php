<?php

namespace SPF\Http;

use SPF;
use SPF\IFace\Http;

class FastCGI implements Http
{
    public function header($k, $v)
    {
        header($k . ': ' . $v);
    }

    public function status($code)
    {
        header('HTTP/1.1 ' . SPF\Response::$HTTP_HEADERS[$code]);
    }

    public function response($content)
    {
        exit($content);
    }

    public function redirect($url, $mode = 302)
    {
        header("HTTP/1.1 " . SPF\Response::$HTTP_HEADERS[$mode]);
        header("Location: " . $url);
    }

    public function finish($content = null)
    {
        exit($content);
    }

    public function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function getRequestBody()
    {
        return file_get_contents('php://input');
    }
}
