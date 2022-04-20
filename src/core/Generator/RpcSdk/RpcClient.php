<?php

namespace SPF\Generator\RpcSdk;

use SPF\Client\RPC;

/**
 * The rpc client only use sdk generate, connot use other condition
 */
class RpcClient
{
    public static function call(string $method, array $args = [], bool $isStatic = false)
    {
        $call = $isStatic ? $method : explode('::', $method);

        return call_user_func([RPC::getInstance(), 'task'], $call, $args)->getResult();
    }
}
