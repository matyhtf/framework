<?php
namespace SPF;

class Cookie
{
    public static $path = '/';
    public static $domain = null;
    public static $secure = false;
    public static $httponly = false;

    public static function get($key, $default = null)
    {
        if (!isset($_COOKIE[$key])) {
            return $default;
        } else {
            return $_COOKIE[$key];
        }
    }

    public static function set($key, $value, $expire = 0)
    {
        if ($expire != 0) {
            $expire = time() + $expire;
        }
        if (defined('SWOOLE_SERVER')) {
            App::getInstance()->http->setcookie(
                $key,
                $value,
                $expire,
                Cookie::$path,
                Cookie::$domain,
                Cookie::$secure,
                Cookie::$httponly
            );
        } else {
            setcookie($key, $value, $expire, Cookie::$path, Cookie::$domain, Cookie::$secure, Cookie::$httponly);
        }
    }

    public static function delete($key)
    {
        unset($_COOKIE[$key]);
        self::set($key, '');
    }
}
