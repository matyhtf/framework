<?php
namespace SPF;

class Http
{
    static function __callStatic($func, $params)
    {
        return call_user_func_array(array(App::getInstance()->http, $func), $params);
    }

    static function buildQuery($array)
    {
        if (!is_array($array))
        {
            return false;
        }
        $query = array();
        foreach($array as $k => $v)
        {
            $query[] = ($k.'='.urlencode($v));
        }
        return implode("&", $query);
    }
}
