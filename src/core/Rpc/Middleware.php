<?php

namespace SPF\Rpc;

interface Middleware
{
    /**
     * @param array $request
     * @param \Closure $next
     *
     * @return $next($request)
     */
    public static function handle(array &$request, \Closure $next, ...$args);
}
