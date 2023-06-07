<?php

namespace SPF\Queue;

use SPF;

/**
 * 这里没办法保证原子性，请线上服务使用redis，httpsqs或系统的ipcs消息队列
 */
class Cache implements SPF\IFace\Queue
{
    /**
     * @var SPF\IFace\Cache
     */
    private $cache;
    private $start_id = 1;
    private $end_id = 1;
    private $compress = false;
    private $compress_level = 9;

    public $name = 'swoole';
    public $prefix = 'queue_';
    private $cache_prefix;
    public static $cache_lifetime = 0;
    public static $mutex_loop = 100;

    public function __construct($config)
    {
        if (!empty($config['name'])) {
            $this->name = $config['name'];
        }
        if (!empty($config['prefix'])) {
            $this->prefix = $config['prefix'];
        }
        if (!empty($config['cache_key'])) {
            $config['cache_key'] = 'master';
        }
        $this->cache = SPF\App::getInstance()->cache($config['cache_key']);
        $this->init();
    }

    private function init()
    {
        $this->cache_prefix = $this->prefix . $this->name . '_';
        //队列起始ID
        $start_id = $this->cache->get($this->cache_prefix . 'start');
        if ($start_id !== false) {
            $this->start_id = $start_id;
        }
        //队列结束ID
        $end_id = $this->cache->get($this->cache_prefix . 'end');
        if ($end_id !== false) {
            $this->end_id = $end_id;
        }
    }

    public function push($data)
    {
        $c_id = $this->end_id;
        $this->cache->increment($this->cache_prefix . 'end');
        $this->cache->set($this->cache_prefix . $c_id, $data, self::$cache_lifetime);
        $this->cache->save();
        return true;
    }

    public function pop()
    {
        $c_id = $this->start_id;
        $data = $this->cache->get($this->cache_prefix . $c_id);
        if ($data === false) {
            return false;
        } else {
            $this->cache->increment($this->cache_prefix . 'start');
            $this->cache->delete($this->cache_prefix . $c_id);
            $this->cache->save();
            return $data;
        }
    }
}
