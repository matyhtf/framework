<?php

namespace SPF\Cache;

use SPF;

/**
 * Memcache封装类，支持memcache和memcached两种扩展
 * @author Tianfeng.Han
 * @package SPF
 * @subpackage cache
 */
class Memcache implements SPF\IFace\Cache
{
    /**
     * memcached扩展采用libmemcache，支持更多特性，更标准通用
     */
    protected $memcached = false;
    protected $cache;
    //启用压缩
    protected $flags = 0;

    const DEFAULT_PORT = 11211;
    const DEFAULT_HOST = '127.0.0.1';

    function __construct($config)
    {
        /**
         * 没有memcache扩展，PHP7
         */
        if (!extension_loaded('memcache') and extension_loaded('memcached')) {
            $config['use_memcached'] = true;
        }

        if (SPF\App::$enableCoroutine) {
            $this->cache = new SPF\Coroutine\Memcache;
        } elseif (empty($config['use_memcached'])) {
            $this->cache = new \Memcache;
            $this->flags = MEMCACHE_COMPRESSED;
        } else {
            $this->cache = new \Memcached;
            $this->memcached = true;
            $this->cache->setOption(\Memcached::OPT_DISTRIBUTION, \Memcached::DISTRIBUTION_CONSISTENT);
        }

        if (isset($config['compress']) and $config['compress'] === false) {
            $this->flags = 0;
        }

        if (empty($config['servers'])) {
            $this->addServer($config);
        } else {
            foreach ($config['servers'] as $cf) {
                $this->addServer($cf);
            }
        }
    }

    /**
     * 格式化配置
     * @param $cf
     * @return null
     */
    protected function formatConfig(&$cf)
    {
        if (empty($cf['host'])) {
            $cf['host'] = self::DEFAULT_HOST;
        }
        if (empty($cf['port'])) {
            $cf['port'] = self::DEFAULT_PORT;
        }
        if (empty($cf['weight'])) {
            $cf['weight'] = 1;
        }
        if (empty($cf['persistent'])) {
            $cf['persistent'] = true;
        }
    }

    /**
     * 增加节点服务器
     * @param $cf
     * @return null
     */
    protected function addServer($cf)
    {
        $this->formatConfig($cf);
        if ($this->memcached) {
            $this->cache->addServer($cf['host'], $cf['port'], $cf['weight']);
        } else {
            $this->cache->addServer($cf['host'], $cf['port'], $cf['persistent'], $cf['weight']);
        }
    }

    /**
     * 获取数据
     * @param $key
     * @return mixed
     */
    function get($key)
    {
        return $this->cache->get($key);
    }

    /**
     * 设置
     * @param $key
     * @param $value
     * @param int $expire
     * @return bool
     */
    function set($key, $value, $expire = 0)
    {
        if ($this->memcached) {
            return $this->cache->set($key, $value, $expire);
        } else {
            return $this->cache->set($key, $value, $this->flags, $expire);
        }
    }

    /**
     * 删除
     * @param $key
     * @return bool
     */
    function delete($key)
    {
        return $this->cache->delete($key);
    }

    function __call($method, $params)
    {
        return call_user_func_array(array($this->cache, $method), $params);
    }
}