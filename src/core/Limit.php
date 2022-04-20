<?php
namespace SPF;

/**
 * 频率限制组件
 * @package SPF
 */
class Limit
{
    const PREFIX = 'swoole:limit:';
    /**
     * @var \redis
     */
    protected $redis;

    /**
     * 构造方法，需要传入一个redis_id
     * @param $config
     */
    function __construct($config)
    {
        if (empty($config['redis_id']))
        {
            $config['redis_id'] = 'master';
        }
        $this->redis = App::getInstance()->redis($config['redis_id']);
    }

    /**
     * 增加计数
     * @param $key
     * @param int $expire
     * @param int $incrby
     * @return bool
     */
    function addCount($key, $expire = 86400, $incrby = 1)
    {
        $key = self::PREFIX.$key;
        //增加计数
        if ($this->redis->exists($key))
        {
            return $this->redis->incr($key, $incrby);
        }
        //不存在的Key，设置为1
        else
        {
            return $this->redis->set($key, $incrby, $expire);
        }
    }

    /**
     * 检查是否超过了频率限制，如果超过返回false，未超过返回true
     * @param $key
     * @param $limit
     * @return bool
     */
    function exceed($key, $limit)
    {
        $key = self::PREFIX . $key;
        $count = $this->redis->get($key);

        if (!empty($count) and $count > $limit)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 清除频率计数
     * @param $key
     * @return bool
     */
    function reset($key)
    {
        $key = self::PREFIX.$key;
        return $this->redis->del($key);
    }
}