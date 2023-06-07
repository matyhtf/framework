<?php

namespace SPF\Coroutine;

use Swoole\Coroutine;

class BaseContext
{
    protected static $pool = [];

    public static function get($type)
    {
        $context = Coroutine::getContext();
        return $context[$type];
    }

    public static function put($type, $object)
    {
        $context = Coroutine::getContext();
        $context[$type] = $object;
    }

    public static function delete($type, $object)
    {
        $context = Coroutine::getContext();
        unset($context[$type]);
    }
}
