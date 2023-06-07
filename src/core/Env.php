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
    public $swoole;
    
    public function __construct($swoole)
    {
        $this->swoole = $swoole;
    }
    public function offsetGet($key)
    {
        return $this->swoole->cache->get($this->cache_prefix.$key);
    }
    public function offsetSet($key, $value)
    {
        $this->swoole->cache->set($this->cache_prefix.$key, $value, self::$default_cache_life);
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
        $this->swoole->cache->delete($this->cache_prefix.$key);
    }
    public function __toString()
    {
        return "This is a memory Object!";
    }
}
