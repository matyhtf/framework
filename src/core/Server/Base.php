<?php
namespace SPF\Server;

use SPF;

abstract class Base implements Driver
{
    protected static $options = array();
    public $setting = array();
    /**
     * @var SPF\Protocol\WebServer
     */
    public $protocol;
    public $host = '0.0.0.0';
    public $port;
    public $timeout;

    public $runtimeSetting;

    public $buffer_size = 8192;
    public $write_buffer_size = 2097152;
    public $server_block = 0; //0 block,1 noblock
    public $client_block = 0; //0 block,1 noblock

    //最大连接数
    public $max_connect = 1000;
    public $client_num = 0;

    //客户端socket列表
    public $client_sock;
    public $server_sock;
    /**
     * 文件描述符
     * @var array
     */
    public $fds = array();

    protected $processName;

    function __construct($host, $port, $timeout = 30)
    {
        $this->host = $host;
        $this->port = $port;
        $this->timeout = $timeout;
    }

    function addListener($host, $port, $type)
    {
        if (!($this->protocol instanceof SPF\Network\Server))
        {
            throw new \Exception("addListener must use swoole extension.");
        }
    }

    /**
     * 设置进程名称
     * @param $name
     */
    function setProcessName($name)
    {
        $this->processName = $name;
    }

    /**
     * 获取进程名称
     * @return string
     */
    function getProcessName()
    {
        if (empty($this->processName))
        {
            global $argv;
            return "php {$argv[0]}";
        }
        else
        {
            return $this->processName;
        }
    }

    /**
     * 设置通信协议
     * @param $protocol
     * @throws \Exception
     */
    function setProtocol($protocol)
    {
        if (!($protocol instanceof SPF\IFace\Protocol))
        {
            throw new \Exception("The protocol is not instanceof \\SPF\\IFace\\Protocol");
        }
        $this->protocol = $protocol;
        $protocol->server = $this;
    }

    /**
     * 设置选项
     * @param $key
     * @param $value
     */
    static function setOption($key, $value)
    {
        self::$options[$key] = $value;
    }

    function connection_info($fd)
    {
        $peername = stream_socket_get_name($this->fds[$fd], true);
        list($ip, $port) = explode(':', $peername);
        return array('remote_port' => $port, 'remote_ip' => $ip);
    }

    /**
     * 接受连接
     * @return bool|int
     */
    function accept()
    {
        $client_socket = stream_socket_accept($this->server_sock, 0);
        //惊群
        if ($client_socket === false)
        {
            return false;
        }
        $client_socket_id = (int)$client_socket;
        stream_set_blocking($client_socket, $this->client_block);
        $this->client_sock[$client_socket_id] = $client_socket;
        $this->client_num++;
        if ($this->client_num > $this->max_connect)
        {
            SPF\Network\Stream::close($client_socket);
            return false;
        }
        else
        {
            //设置写缓冲区
            stream_set_write_buffer($client_socket, $this->write_buffer_size);
            return $client_socket_id;
        }
    }

    function spawn($setting)
    {
        $num = 0;
        if (isset($setting['worker_num']))
        {
            $num = (int)$setting['worker_num'];
        }
        if ($num < 2)
        {
            return;
        }
        if (!extension_loaded('pcntl'))
        {
            die(__METHOD__ . " require pcntl extension!");
        }
        $pids = array();
        for ($i = 0; $i < $num; $i++)
        {
            $pid = pcntl_fork();
            if ($pid > 0)
            {
                $pids[] = $pid;
            }
            else
            {
                break;
            }
        }
        return $pids;
    }

    function startWorker()
    {

    }

    abstract function run($setting);

    /**
     * 发送数据到客户端
     * @param $client_id
     * @param $data
     * @return bool
     */
    abstract function send($client_id, $data);

    /**
     * 关闭连接
     * @param $client_id
     * @return mixed
     */
    abstract function close($client_id);

    abstract function shutdown();

    function daemonize()
    {
        if (!function_exists('pcntl_fork'))
        {
            throw new \Exception(__METHOD__ . ": require pcntl_fork.");
        }
        $pid = pcntl_fork();
        if ($pid == -1)
        {
            die("fork(1) failed!\n");
        }
        elseif ($pid > 0)
        {
            //让由用户启动的进程退出
            exit(0);
        }

        //建立一个有别于终端的新session以脱离终端
        posix_setsid();

        $pid = pcntl_fork();
        if ($pid == -1)
        {
            die("fork(2) failed!\n");
        }
        elseif ($pid > 0)
        {
            //父进程退出, 剩下子进程成为最终的独立进程
            exit(0);
        }
    }

    function onError($errno, $errstr)
    {
        exit("$errstr ($errno)");
    }

    /**
     * 创建一个Stream Server Socket
     * @param $uri
     * @param int $block
     * @return resource
     */
    function create($uri, $block = 0)
    {
        if (swoole_string($uri)->startsWith('udp')) {
            $socket = stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND);
        } else {
            $socket = stream_socket_server($uri, $errno, $errstr);
        }

        if (!$socket) {
            $this->onError($errno, $errstr);
        }
        //设置socket为非堵塞或者阻塞
        stream_set_blocking($socket, $block);
        return $socket;
    }

    function create_socket($uri, $block = false)
    {
        $set = parse_url($uri);
        if (swoole_string($uri)->startsWith('udp')) {
            $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        } else {
            $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        }

        if ($block) {
            socket_set_block($sock);
        } else {
            socket_set_nonblock($sock);
        }
        socket_bind($sock, $set['host'], $set['port']);
        socket_listen($sock);
        return $sock;
    }

    function sendData($sock, $data)
    {
        return SPF\Network\Stream::write($sock, $data);
    }

    function log($log)
    {
        echo $log, NL;
    }
}

function sw_run($cmd)
{
    if (PHP_OS == 'WINNT')
    {
        pclose(popen("start /B " . $cmd, "r"));
    }
    else
    {
        exec($cmd . " > /dev/null &");
    }
}

function sw_gc_array($array)
{
    $new = array();
    foreach ($array as $k => $v)
    {
        $new[$k] = $v;
        unset($array[$k]);
    }
    unset($array);
    return $new;
}

interface TCP_Server_Driver
{
    function run($num = 1);

    function send($client_id, $data);

    function close($client_id);

    function shutdown();

    function setProtocol($protocol);
}

interface UDP_Server_Driver
{
    function run($num = 1);

    function shutdown();

    function setProtocol($protocol);
}

interface TCP_Server_Protocol
{
    function onStart();

    function onConnect($client_id);

    function onReceive($client_id, $data);

    function onClose($client_id);

    function onShutdown($server);
}

interface UDP_Server_Protocol
{
    function onStart();

    function onData($peer, $data);

    function onShutdown();
}
