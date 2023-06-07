<?php

namespace SPF\Coroutine\Component\Hook;

use SPF\Coroutine\Component\Base;
use SPF\Component\Redis as CoRedis;

class Redis extends Base
{
    protected $type = 'redis';

    public function __construct($config)
    {
        parent::__construct($config);
        \SPF\App::getInstance()->beforeAction([$this, '_createObject'], \SPF\App::coroModuleRedis);
        \SPF\App::getInstance()->afterAction([$this, '_freeObject'], \SPF\App::coroModuleRedis);
    }


    public function create()
    {
        return new CoRedis($this->config);
    }

    /**
     * 调用$driver的自带方法
     * @param $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args = array())
    {
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }
        return $redis->{$method}(...$args);
    }
}
