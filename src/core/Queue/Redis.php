<?php
namespace SPF\Queue;

use SPF;

/**
 * Redis内存队列
 */
class Redis implements SPF\IFace\Queue
{
    protected $redis_factory_key;
    protected $key = 'swoole:queue';

    public function __construct($config)
    {
        if (empty($config['id'])) {
            $config['id'] = 'master';
        }
        $this->redis_factory_key = $config['id'];
        if (!empty($config['key'])) {
            $this->key = $config['key'];
        }
    }

    /**
     * 出队
     * @return bool|mixed
     */
    public function pop()
    {
        $ret = SPF\App::getInstance()->redis($this->redis_factory_key)->lPop($this->key);
        if ($ret) {
            return unserialize($ret);
        } else {
            return false;
        }
    }

    /**
     * 入队
     * @param $data
     * @return int
     */
    public function push($data)
    {
        return SPF\App::getInstance()->redis($this->redis_factory_key)->lPush($this->key, serialize($data));
    }
}
