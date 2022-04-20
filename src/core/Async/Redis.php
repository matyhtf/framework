<?php
namespace SPF\Async;

use SPF;

class Redis extends Pool
{
    const DEFAULT_PORT = 6379;

    function __construct($config = array(), $poolSize = 100)
    {
        if (empty($config['host']))
        {
            throw new SPF\Exception\InvalidParam("require redis host option.");
        }
        if (empty($config['port']))
        {
            $config = self::DEFAULT_PORT;
        }
        parent::__construct($config, $poolSize);
        $this->create(array($this, 'connect'));
    }

    protected function connect()
    {
        $redis = new \swoole_redis();

        $redis->on('close', function ($redis)
        {
            $this->remove($redis);
        });

        return $redis->connect($this->config['host'], $this->config['port'], function ($redis, $result)
        {
            if ($result)
            {
                $this->join($redis);
            }
            else
            {
                $this->failure();
                trigger_error("connect to redis server[{$this->config['host']}:{$this->config['port']}] failed. Error: {$redis->errMsg}[{$redis->errCode}].");
            }
        });
    }

    function __call($call, $params)
    {
        return $this->request(function (\swoole_redis $redis) use ($call, $params)
        {
            call_user_func_array(array($redis, $call), $params);
            //必须要释放资源，否则无法被其他重复利用
            $this->release($redis);
        });
    }
}