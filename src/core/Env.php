<?php

namespace SPF;

/**
 * 缓存数组映射模式
 * 可以像访问数组一样读取缓存
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage base
 */
class Env implements \ArrayAccess
{
    public static $default_cache_life = 600;
    public $cache_prefix = 'swoole_env_';
    /**
     * @var App
     */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function offsetGet($key)
    {
        return $this->app->cache->get($this->cache_prefix . $key);
    }

    public function offsetSet($key, $value)
    {
        $this->app->cache->set($this->cache_prefix . $key, $value, self::$default_cache_life);
    }

    public function offsetExists($key)
    {
        $v = $this->offsetGet($key);
        if (is_numeric($v)) {
            return true;
        } else {
            return false;
        }
    }

    public function offsetUnset($key)
    {
        $this->app->cache->delete($this->cache_prefix . $key);
    }

    public function __toString()
    {
        return "This is a memory Object!";
    }
}
