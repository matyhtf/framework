<?php
namespace SPF\Protocol;
use SPF;

abstract class WebServer extends Base
{
    const SOFTWARE = "SwooleFramework";
    const POST_MAXSIZE = 2000000; //POST最大2M
    const DEFAULT_PORT = 8888;
    const DEFAULT_HOST = '0.0.0.0';

    public $config = array();

    /**
     * @var \SPF\Http\Parser
     */
    protected $parser;

    protected $mime_types;
    protected $static_dir;
    protected $static_ext;
    protected $dynamic_ext;
    protected $document_root;
    protected $deny_dir;

    protected $keepalive = false;
    protected $gzip = false;
    protected $expire = false;

    /**
     * @var \SPF\Request;
     */
    public $currentRequest;
    /**
     * @var \SPF\Response;
     */
    public $currentResponse;

    public $requests = array(); //保存请求信息,里面全部是Request对象

    function __construct($config = array())
    {
        define('SWOOLE_SERVER', true);
        SPF\Error::$echo_html = true;
    }

    function setDocumentRoot($path)
    {
        $this->document_root = $path;
    }

    /**
     * 设置应用路径，仅对AppServer和AppFPM有效
     * @param $path
     */
    function setAppPath($path)
    {
        $this->apps_path = $path;
    }

    /**
     * 得到请求对象
     * @param $fd
     * @return SPF\Request
     */
    function getRequest($fd)
    {
        return $this->requests[$fd];
    }

    /**
     * 从ini文件中加载配置
     * @param $ini_file
     */
    function loadSetting($ini_file)
    {
        if (!is_file($ini_file)) exit("SPF AppServer配置文件错误($ini_file)\n");
        $config = parse_ini_file($ini_file, true);
        /*--------------Server------------------*/
        //开启http keepalive
        if (!empty($config['server']['keepalive']))
        {
            $this->keepalive = true;
        }
        //是否压缩
        if (!empty($config['server']['gzip_open']) and function_exists('gzdeflate'))
        {
            $this->gzip = true;
            //default level
            if (empty($config['server']['gzip_level']))
            {
                $config['server']['gzip_level'] = 1;
            }
            //level [1, 9]
            elseif ($config['server']['gzip_level'] > 9)
            {
                $config['server']['gzip_level'] = 9;
            }
        }
        //过期控制
        if (!empty($config['server']['expire_open']))
        {
            $this->expire = true;
            if (empty($config['server']['expire_time']))
            {
                $config['server']['expire_time'] = 1800;
            }
        }
        /*--------------Session------------------*/
        if (empty($config['session']['cookie_life'])) $config['session']['cookie_life'] = 86400; //保存SESSION_ID的cookie存活时间
        if (empty($config['session']['session_life'])) $config['session']['session_life'] = 1800; //Session在Cache中的存活时间
        if (empty($config['session']['cache_url'])) $config['session']['cache_url'] = 'file://localhost#sess'; //Session在Cache中的存活时间
        /*--------------Apps------------------*/
        if (empty($config['apps']['url_route'])) $config['apps']['url_route'] = 'url_route_default';
        if (empty($config['apps']['auto_reload'])) $config['apps']['auto_reload'] = 0;
        if (empty($config['apps']['charset'])) $config['apps']['charset'] = 'utf-8';

        if (!empty($config['access']['post_maxsize']))
        {
            $this->config['server']['post_maxsize'] = $config['access']['post_maxsize'];
        }
        if (empty($config['server']['post_maxsize']))
        {
            $config['server']['post_maxsize'] = self::POST_MAXSIZE;
        }
        /*--------------Access------------------*/
        $this->deny_dir = array_flip(explode(',', $config['access']['deny_dir']));
        $this->static_dir = array_flip(explode(',', $config['access']['static_dir']));
        $this->static_ext = array_flip(explode(',', $config['access']['static_ext']));
        $this->dynamic_ext = array_flip(explode(',', $config['access']['dynamic_ext']));
        /*--------------document_root------------*/
        if (empty($this->document_root) and !empty($config['server']['document_root']))
        {
            $this->document_root = $config['server']['document_root'];
        }
        /*-----merge----*/
        if (!is_array($this->config))
        {
            $this->config = array();
        }
        $this->config = array_merge($this->config, $config);
    }

    static function create($ini_file = null)
    {
        $opt = getopt('m:h:p:d:');
        //mode, server or fastcgi
        if (empty($opt['m']))
        {
            $opt['m'] = 'server';
        }
        //daemonize
        if (empty($opt['d']))
        {
            $opt['d'] = false;
        }
        if ($opt['m'] == 'fastcgi')
        {
            $protocol = new SPF\Protocol\AppFPM();
        }
        else
        {
            $protocol = new SPF\Protocol\AppServer();
        }
        if ($ini_file)
        {
            $protocol->loadSetting($ini_file); //加载配置文件
        }
        //port
        if (!empty($opt['p']))
        {
            $protocol->default_port = $opt['p'];
        }
        elseif (!empty($protocol->config['server']['port']))
        {
            $protocol->default_port = $protocol->config['server']['port'];
        }
        else
        {
            $protocol->default_port = self::DEFAULT_PORT;
        }
        //host
        if (!empty($opt['h']))
        {
            $protocol->default_host = $opt['h'];
        }
        elseif (!empty($protocol->config['server']['host']))
        {
            $protocol->default_host = $protocol->config['server']['host'];
        }
        else
        {
            $protocol->default_host = self::DEFAULT_HOST;
        }

        $server = SPF\Network\Server::autoCreate($protocol->default_host, $protocol->default_port);
        $server->setProtocol($protocol);

        if (!empty($protocol->config['server']['worker_num']))
        {
            $server->runtimeSetting['worker_num'] = $protocol->config['server']['worker_num'];
        }

        return $protocol;
    }
}