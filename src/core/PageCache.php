<?php
namespace SPF;

/**
 * 页面缓存类
 * Class PageCache
 * @package SPF
 */
class PageCache
{
    public $cache_dir;
    public $expire;

    /**
     * @param int $expire
     * @param string $cache_dir
     */
    public function __construct($expire = 3600, $cache_dir = '')
    {
        $this->expire = $expire;
        if ($cache_dir === '') {
            $this->cache_dir = WEBPATH . '/cache/pages_c';
        } else {
            $this->cache_dir = $cache_dir;
        }
    }

    /**
     * 建立缓存
     * @param $content
     */
    public function create($content)
    {
        file_put_contents($this->cache_dir . '/' . base64_encode($_SERVER['REQUEST_URI']) . '.html', $content);
    }

    /**
     * 加载缓存
     */
    public function load()
    {
        include($this->cache_dir . '/' . base64_encode($_SERVER['REQUEST_URI']) . '.html');
    }

    /**
     * 检查是否存在有效缓存
     * @return bool
     */
    public function isCached()
    {
        $file = $this->cache_dir . '/' . base64_encode($_SERVER['REQUEST_URI']) . '.html';
        if (!file_exists($file)) {
            return false;
        } elseif (filemtime($file) + $this->expire < time()) {
            return false;
        } else {
            return true;
        }
    }
}
