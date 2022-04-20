<?php
namespace SPF\Network;

use SPF;
use SPF\Server\Base;
use SPF\Server\Driver;

/**
 * Class Server
 * @package SPF\Network
 */
class Server extends Base implements Driver
{
    protected static $startFunction;
    protected static $beforeStopCallback;
    protected static $beforeReloadCallback;

    static $swooleMode;
    static $useSwooleHttpServer = false;
    static $optionKit;
    static $pidFile;

    static $defaultOptions = array(
        'd|daemon' => '启用守护进程模式',
        'h|host?' => '指定监听地址',
        'p|port?' => '指定监听端口',
        'help' => '显示帮助界面',
        'b|base' => '使用BASE模式启动',
        'w|worker?' => '设置Worker进程的数量',
        'r|thread?' => '设置Reactor线程的数量',
        't|tasker?' => '设置Task进程的数量',
    );

    /**
     * @var \swoole_server
     */
    protected $sw;
    protected $pid_file;
    static public $swoole;

    /**
     * 设置PID文件
     * @param $pidFile
     */
    static function setPidFile($pidFile)
    {
        self::$pidFile = $pidFile;
    }

    /**
     * 杀死所有进程
     * @param $name
     * @param int $signo
     * @return string
     */
    static function killProcessByName($name, $signo = 9)
    {
        $cmd = 'ps -eaf |grep "' . $name . '" | grep -v "grep"| awk "{print $2}"|xargs kill -'.$signo;
        return exec($cmd);
    }

    /**
     *
     * $opt->add( 'f|foo:' , 'option requires a value.' );
     * $opt->add( 'b|bar+' , 'option with multiple value.' );
     * $opt->add( 'z|zoo?' , 'option with optional value.' );
     * $opt->add( 'v|verbose' , 'verbose message.' );
     * $opt->add( 'd|debug'   , 'debug message.' );
     * $opt->add( 'long'   , 'long option name only.' );
     * $opt->add( 's'   , 'short option name only.' );
     *
     * @param $specString
     * @param $description
     * @throws ServerOptionException
     */
    static function addOption($specString, $description)
    {
        if (!self::$optionKit)
        {
            SPF\App::getInstance()->loader->addNameSpace('GetOptionKit', dirname(dirname(__DIR__)) . '/module/GetOptionKit/src/GetOptionKit');
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }
        foreach (self::$defaultOptions as $k => $v)
        {
            if ($k[0] == $specString[0])
            {
                throw new ServerOptionException("不能添加系统保留的选项名称");
            }
        }
        //解决Windows平台乱码问题
        if (PHP_OS == 'WINNT')
        {
            $description = iconv('utf-8', 'gbk', $description);
        }
        self::$optionKit->add($specString, $description);
    }


    static function setOption($key, $value)
    {
        self::$options[$key] = $value;
    }

    /**
     * @param $function
     */
    static function setStartFunction(callable $function)
    {
        self::$startFunction = $function;
    }

    /**
     * @param callable $function
     */
    static function beforeStop(callable $function)
    {
        self::$beforeStopCallback = $function;
    }

    /**
     * @param callable $function
     */
    static function beforeReload(callable $function)
    {
        self::$beforeReloadCallback = $function;
    }

    static function getServerPid()
    {
        if (empty(self::$pidFile))
        {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file))
        {
            $server_pid = file_get_contents($pid_file);
        }
        else
        {
            $server_pid = 0;
        }
        return $server_pid;
    }
    /**
     * 显示命令行指令
     * @param $startFunction
     * @param null $usage
     * @throws \Exception
     */
    static function start($startFunction, $usage = null)
    {
        if (empty(self::$pidFile))
        {
            throw new \Exception("require pidFile.");
        }
        $pid_file = self::$pidFile;
        if (is_file($pid_file))
        {
            $server_pid = file_get_contents($pid_file);
        }
        else
        {
            $server_pid = 0;
        }

        if (!self::$optionKit)
        {
            SPF\App::getInstance()->loader->addNameSpace('GetOptionKit', dirname(dirname(__DIR__)) . '/module/GetOptionKit/src/GetOptionKit');
            self::$optionKit = new \GetOptionKit\GetOptionKit;
        }

        $kit = self::$optionKit;
        foreach(self::$defaultOptions as $k => $v)
        {
            //解决Windows平台乱码问题
            if (PHP_OS == 'WINNT')
            {
                $v = iconv('utf-8', 'gbk', $v);
            }
            $kit->add($k, $v);
        }
        global $argv;
        $opt = $kit->parse($argv);
        if (empty($argv[1]) or isset($opt['help']))
        {
            goto usage;
        }
        elseif ($argv[1] == 'reload')
        {
            if (empty($server_pid))
            {
                exit("Server is not running");
            }
            if (self::$beforeReloadCallback)
            {
                call_user_func(self::$beforeReloadCallback, $opt);
            }
            SPF\App::getInstance()->os->kill($server_pid, SIGUSR1);
            exit;
        }
        elseif ($argv[1] == 'stop')
        {
            if (empty($server_pid))
            {
                exit("Server is not running\n");
            }
            if (self::$beforeStopCallback)
            {
                call_user_func(self::$beforeStopCallback, $opt);
            }
            SPF\App::getInstance()->os->kill($server_pid, SIGTERM);
            exit;
        }
        elseif ($argv[1] == 'start')
        {
            //已存在ServerPID，并且进程存在
            if (!empty($server_pid) and SPF\App::getInstance()->os->kill($server_pid, 0))
            {
                exit("Server is already running.\n");
            }
        }
        else
        {
            usage:
            if ($usage != null)
            {
                $tips = $usage;
            }
            else
            {
                $tips = "php {$argv[0]} start|stop|reload";
            }
            $kit->specs->printOptions($tips);
            exit;
        }
        self::$options = $opt;
        $startFunction($opt);
    }


    static function startServer($isHttp = false)
    {
        if ($isHttp)
        {
            self::$useSwooleHttpServer = true;
        }
        $server_pid = self::getServerPid();
        if (!empty($server_pid) and SPF\App::getInstance()->os->kill($server_pid, 0))
        {
            return self::cmdStatus(1, "Server is running on PID: {$server_pid}");
        }
        if (empty(self::$startFunction) or !is_callable(self::$startFunction))
        {
            throw new \Exception("startFunction is invalid");
        }
        $startFunction = self::$startFunction;
        $startFunction($isHttp);
        return self::cmdStatus(0, "Server start success");
    }

    static function stop()
    {
        $pid = self::getServerPid();
        if (empty($pid))
        {
            return self::cmdStatus(1, "get pid failed");
        }

        if (!empty($pid) and !SPF\App::getInstance()->os->kill($pid, 0))
        {
            return self::cmdStatus(1, "Server is not running");
        }

        if (self::$beforeStopCallback)
        {
            call_user_func(self::$beforeStopCallback);
        }
        SPF\App::getInstance()->os->kill($pid, SIGTERM);
        return self::cmdStatus(0, "Server stop success");
    }

    static function reload()
    {
        $pid = self::getServerPid();

        if (empty($pid))
        {
            return self::cmdStatus(1, "get pid failed");
        }

        if (!empty($pid) and !SPF\App::getInstance()->os->kill($pid, 0))
        {
            return self::cmdStatus(1, "Server is not running");
        }

        if (self::$beforeReloadCallback)
        {
            call_user_func(self::$beforeReloadCallback);
        }
        SPF\App::getInstance()->os->kill($pid, SIGUSR1);
        return self::cmdStatus(0, "Server reload success");
    }

    static function cmdStatus($code, $msg)
    {
        return [
            'code' => $code,
            'msg' => $msg
        ];
    }

    /**
     * 自动推断扩展支持
     * 默认使用swoole扩展,其次是libevent,最后是select(支持windows)
     * @param      $host
     * @param      $port
     * @param bool $ssl
     * @return Server
     */
    static function autoCreate($host, $port, $ssl = false)
    {
        if (class_exists('\\Swoole\\Server', false))
        {
            return new self($host, $port, $ssl);
        }
        elseif (function_exists('event_base_new'))
        {
            return new EventTCP($host, $port, $ssl);
        }
        else
        {
            return new SelectTCP($host, $port, $ssl);
        }
    }

    function __construct($host, $port, $ssl = false)
    {
        $flag = $ssl ? (SWOOLE_SOCK_TCP | SWOOLE_SSL) : SWOOLE_SOCK_TCP;
        if (!empty(self::$options['base']))
        {
            self::$swooleMode = SWOOLE_BASE;
        }
        elseif (extension_loaded('swoole'))
        {
            self::$swooleMode = SWOOLE_PROCESS;
        }

        if (!empty(self::$options['host']) and !empty(self::$options['port']))
        {
            $this->host = self::$options['host'];
            $this->port = self::$options['port'];
        }
        else
        {
            $this->host = $host;
            $this->port = $port;
        }

        if (self::$useSwooleHttpServer)
        {
            $this->sw = new \swoole_http_server($this->host, $this->port, self::$swooleMode, $flag);
        }
        else
        {
            $this->sw = new \swoole_server($this->host, $this->port, self::$swooleMode, $flag);
        }

        SPF\Error::$stop = false;
        SPF\JS::$return = true;
        $this->runtimeSetting = array(
            'backlog' => 128,        //listen backlog
        );
    }

    function daemonize()
    {
        $this->runtimeSetting['daemonize'] = 1;
    }

    function connections()
    {
        return $this->sw->connections;
    }

    function connection_info($fd)
    {
        return $this->sw->connection_info($fd);
    }

    function onMasterStart($serv)
    {
        SPF\Console::setProcessName($this->getProcessName() . ': master -host=' . $this->host . ' -port=' . $this->port);
        if (!empty($this->runtimeSetting['pid_file']))
        {
            file_put_contents(self::$pidFile, $serv->master_pid);
        }
        if (method_exists($this->protocol, 'onMasterStart'))
        {
            $this->protocol->onMasterStart($serv);
        }
    }

    function onMasterStop($serv)
    {
        if (!empty($this->runtimeSetting['pid_file']))
        {
            unlink(self::$pidFile);
        }
        if (method_exists($this->protocol, 'onMasterStop'))
        {
            $this->protocol->onMasterStop($serv);
        }
    }

    function onManagerStart($server)
    {
        SPF\Console::setProcessName($this->getProcessName() . ': manager');
        if (method_exists($this->protocol, 'onManagerStart'))
        {
            $this->protocol->onManagerStart($server);
        }
    }

    function onManagerStop($server)
    {
        if (method_exists($this->protocol, 'onManagerStop'))
        {
            $this->protocol->onManagerStop($server);
        }
    }

    function onWorkerStart($serv, $worker_id)
    {
        /**
         * 清理Opcache缓存
         */
        if (function_exists('opcache_reset'))
        {
            opcache_reset();
        }
        /**
         * 清理APC缓存
         */
        if (function_exists('apc_clear_cache'))
        {
            apc_clear_cache();
        }

        if ($worker_id >= $serv->setting['worker_num'])
        {
            SPF\Console::setProcessName($this->getProcessName() . ': task');
        }
        else
        {
            SPF\Console::setProcessName($this->getProcessName() . ': worker');
        }
        if (method_exists($this->protocol, 'onStart'))
        {
            $this->protocol->onStart($serv, $worker_id);
        }
        if (method_exists($this->protocol, 'onWorkerStart'))
        {
            $this->protocol->onWorkerStart($serv, $worker_id);
        }
    }

    function run($setting = array())
    {
        $this->runtimeSetting = array_merge($this->runtimeSetting, $setting);
        if (self::$pidFile)
        {
            $this->runtimeSetting['pid_file'] = self::$pidFile;
        }
        if (!empty(self::$options['daemon']))
        {
            $this->runtimeSetting['daemonize'] = true;
        }
        if (!empty(self::$options['worker']))
        {
            $this->runtimeSetting['worker_num'] = intval(self::$options['worker']);
        }
        if (!empty(self::$options['thread']))
        {
            $this->runtimeSetting['reator_num'] = intval(self::$options['thread']);
        }
        if (!empty(self::$options['tasker']))
        {
            $this->runtimeSetting['task_worker_num'] = intval(self::$options['tasker']);
        }

        $this->log("server settings=".var_export($this->runtimeSetting, true));
        $this->sw->set($this->runtimeSetting);
        $this->sw->on('Start', array($this, 'onMasterStart'));
        $this->sw->on('Shutdown', array($this, 'onMasterStop'));
        $this->sw->on('ManagerStart', array($this, 'onManagerStart'));
        $this->sw->on('ManagerStop', array($this, 'onManagerStop'));
        $this->sw->on('WorkerStart', array($this, 'onWorkerStart'));

        if (is_callable(array($this->protocol, 'onConnect')))
        {
            $this->sw->on('Connect', array($this->protocol, 'onConnect'));
        }
        if (is_callable(array($this->protocol, 'onClose')))
        {
            $this->sw->on('Close', array($this->protocol, 'onClose'));
        }
        if (self::$useSwooleHttpServer)
        {
            $this->sw->on('Request', array($this->protocol, 'onRequest'));
        }
        else
        {
            $this->sw->on('Receive', array($this->protocol, 'onReceive'));
        }
        if (is_callable(array($this->protocol, 'WorkerStop')))
        {
            $this->sw->on('WorkerStop', array($this->protocol, 'WorkerStop'));
        }
        //swoole-1.8已经移除了onTimer回调函数
        if (version_compare(SWOOLE_VERSION, '1.8.0') < 0)
        {
            if (is_callable(array($this->protocol, 'onTimer')))
            {
                $this->sw->on('Timer', array($this->protocol, 'onTimer'));
            }
        }

        if (is_callable(array($this->protocol, 'onTask')))
        {
            $this->sw->on('Task', array($this->protocol, 'onTask'));
            $this->sw->on('Finish', array($this->protocol, 'onFinish'));
        }
        self::$swoole = $this->sw;
        $this->sw->start();
    }

    function shutdown()
    {
        return $this->sw->shutdown();
    }

    function close($client_id)
    {
        return $this->sw->close($client_id);
    }

    /**
     * @param $protocol
     * @throws \Exception
     */
    function setProtocol($protocol)
    {
        if (self::$useSwooleHttpServer)
        {
            $this->protocol = $protocol;
        }
        else
        {
            parent::setProtocol($protocol);
        }
    }

    function send($client_id, $data)
    {
        return $this->sw->send($client_id, $data);
    }

    static function task($data,$func)
    {
        $params = array(
            'func' => $func,
            'data' => $data,
        );
        self::$swoole->task($params);
    }

    function __call($func, $params)
    {
        return call_user_func_array(array($this->sw, $func), $params);
    }
}

class ServerOptionException extends \Exception
{

}
