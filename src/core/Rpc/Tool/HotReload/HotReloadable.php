<?php

namespace SPF\Rpc\Tool\HotReload;

interface HotReloadable
{
    /**
     * @param callable|string|array $callback
     *
     * @return self
     */
    public function setCallback($callback);

    /**
     * @return \swoole_process
     */
    public function make();
}
