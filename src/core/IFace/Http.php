<?php
namespace SPF\IFace;

interface Http
{
    public function header($k, $v);

    public function status($code);

    public function response($content);

    public function redirect($url, $mode = 302);

    public function finish($content = null);

    public function setcookie(
        $name,
        $value = null,
        $expire = null,
        $path = '/',
        $domain = null,
        $secure = null,
        $httponly = null
    );
}
