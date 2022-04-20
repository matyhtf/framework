<?php

namespace SPF\Coroutine;

use Swoole\Coroutine;

class BaseContext
{
    protected static $pool = [];

    static function get($type)
    {
        $context = Coroutine::getContext();
        return $context[$type];
    }

    static function put($type, $object)
    {
        $context = Coroutine::getContext();
        $context[$type] = $object;
    }

    static function delete($type, $object)
    {
        $context = Coroutine::getContext();
        unset($context[$type]);
    }
}
