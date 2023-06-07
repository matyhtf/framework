<?php
namespace SPF;

use SPF\Client\CURL;

class URL
{
    const CACHE_LIFETIME = 300;
    const CACHE_KEY_PREFIX = 'swoole_urlcache_';

    public $config;
    public $info;
    /**
     * @var CURL
     */
    protected $_curl;

    public function __construct($config)
    {
        if (empty($config) or empty($config['url'])) {
            throw new \Exception("require url.");
        }

        if (!empty($config['cache'])) {
            if (empty($config['lifetime'])) {
                $config['lifetime'] = self::CACHE_LIFETIME;
            }
        }

        $this->_curl = new CURL(!empty($config['debug']));
        $this->config = $config;

        if (!empty($config['setting'])) {
            foreach ($config['setting'] as $k => $v) {
                call_user_func_array(array($this->_curl, 'set'.ucfirst($k)), $v);
            }
        }
    }

    public function get($params = null, $cache_id = '')
    {
        $url = $this->config['url'];

        if ($params) {
            if (Tool::endchar($url) == '&') {
                $url .= http_build_query($params);
            } else {
                $url .= '?' . http_build_query($params);
            }
        }

        if (!empty($this->config['cache'])) {
            if (empty($cache_id)) {
                $cache_id = md5($url);
            }
            $cache_key = self::CACHE_KEY_PREFIX.$cache_id;
            $result = App::getInstance()->cache->get($cache_key);
            if ($result) {
                return $result;
            }
        }

        $result = $this->_curl->get($url);
        if ($result and $this->_curl->info['http_code'] == 200) {
            if (!empty($this->config['json'])) {
                $result = json_decode($result, true);
            } elseif (!empty($this->config['serialize'])) {
                $result = unserialize($result);
            }
            if (!empty($this->config['cache'])) {
                App::getInstance()->cache->set($cache_key, $result, $this->config['lifetime']);
            }
        }
        $this->info = $this->_curl->info;
        return $result;
    }

    public function post($data)
    {
        $this->_curl->post($this->config['url'], $data);
    }
}
