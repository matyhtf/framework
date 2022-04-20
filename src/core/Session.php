<?php
namespace SPF;
use RuntimeException;

/**
 * 会话控制类
 * 通过SPF\Cache系统实现会话控制，可支持FileCache, DBCache, Memcache以及更多
 * @author Tianfeng.Han
 * @package SPF
 */
class Session
{
    protected $config;

    // 类成员属性定义
    static $cache_prefix = "phpsess_";
    static $cookie_key = 'PHPSESSID';
    static $sess_size = 32;

    public $isStarted = false;
    protected $sessID;
    protected $readonly;

    /**
     * @var IFace\Cache
     */
    protected $cache;

    /**
     * 使用PHP内建的SESSION
     * @var bool
     */
    public $use_php_session = true;

    protected $cookie_lifetime = 86400000;
    protected $session_lifetime = 0;
    protected $cookie_domain = null;
    protected $cookie_path = '/';

    public function __construct($config)
    {
        $this->config = $config;
        if (isset($config['use_php_session']) and $config['use_php_session']) {
            $this->use_php_session = true;
            return;
        }
        if (isset($config['cache_id']) && $config['cache_id']) {
            $this->cache = Factory::getCache($config['cache_id']);
        }
        if (isset($config['cookie_lifetime'])) {
            $this->cookie_lifetime = intval($config['cookie_lifetime']);
        }
        if (isset($config['cookie_path'])) {
            $this->cookie_path = $config['cookie_path'];
        }
        if (isset($config['cookie_domain'])) {
            $this->cookie_domain = $config['cookie_domain'];
        }
        if (isset($config['session_lifetime'])) {
            $this->session_lifetime = intval($config['session_lifetime']);
        }
        $this->use_php_session = false;
        App::getInstance()->afterRequest(array($this, 'save'));
    }

    /**
     * 启动会话
     * @param bool $readonly
     * @throws RuntimeException
     */
    public function start($readonly = false)
    {
        if (empty(App::getInstance()->request)) {
            throw new RuntimeException("The method must be used when requested.");
        }

        $this->isStarted = true;
        if ($this->use_php_session) {
            session_start();
        } else {
            $this->readonly = $readonly;
            $sessid = $this->sessID;
            if (empty($sessid)) {
                $sessid = Cookie::get(self::$cookie_key);
                if (empty($sessid)) {
                    $sessid = RandomKey::randmd5(40);
                    App::getInstance()->http->setCookie(self::$cookie_key, $sessid, time() + $this->cookie_lifetime,
                        $this->cookie_path, $this->cookie_domain);
                }
            }
            $_SESSION = $this->load($sessid);
        }
        App::getInstance()->request->session = $_SESSION;
    }

    /**
     * 设置SessionID
     * @param $session_id
     */
    function setId($session_id)
    {
        $this->sessID = $session_id;
        if ($this->use_php_session) {
            session_id($session_id);
        }
    }

    /**
     * 获取SessionID
     * @return string
     */
    function getId()
    {
        if ($this->use_php_session) {
            return session_id();
        } else {
            return $this->sessID;
        }
    }

    /**
     * 加载Session
     * @param $sessId
     * @return array
     */
    public function load($sessId)
    {
        $this->sessID = $sessId;
        $data = $this->cache->get(self::$cache_prefix . $sessId);
        if (!empty($data)) {
            return unserialize($data);
        } else {
            return array();
        }
    }

    /**
     * 保存Session
     * @return bool
     */
    public function save()
    {
        /**
         * 使用PHP Sesion，Readonl，未启动 这3种情况下不需要保存
         */
        if ($this->use_php_session or !$this->isStarted or $this->readonly)
        {
            return true;
        }
        //设置为Session关闭状态
        $this->isStarted = false;
        $key = self::$cache_prefix . $this->sessID;
        return $this->cache->set($key, serialize($_SESSION), $this->session_lifetime);
    }
}
