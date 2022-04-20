<?php
namespace SPF\IFace;

interface Http
{
    function header($k, $v);

    function status($code);

    function response($content);

    function redirect($url, $mode = 302);

    function finish($content = null);

    function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null,
        $httponly = null);
}