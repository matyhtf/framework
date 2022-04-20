<?php
namespace SPF\Client;
use SPF\Core;
use SPF\Exception\InvalidParam;
use SPF\Network\Server;
use SPF\Protocol\RPCServer;
use SPF\Struct\Response;
use SPF\Tool;

/**
 * RPC客户端
 * 1005: 支持单连接并发
 * 1003: 增加重连功能
 * @package SPF\Client
 */
class RPC
{
    const OK = 0;

    /**
     * 版本号
     */
    const VERSION = 1005;

    /**
     * Server的实例列表
     * @var array
     */
    protected $servers = array();

    protected $requestIndex = 0;

    protected $env = array();

    /**
     * 连接到服务器
     * @var array
     */
    protected $connections = array();

    protected $waitList = array();
    protected $timeout = 0.5;
    protected $packet_maxlen = 2097152;   //最大不超过2M的数据包

    /**
     * 启用长连接
     * @var bool
     */
    protected $keepConnection = false;

    protected $haveSwoole = false;
    protected $haveSockets = false;

    protected static $_instances = array();

    protected $encode_gzip = false;
    protected $encode_type = RPCServer::DECODE_PHP;

    protected $user;
    protected $password;

    private $keepSocket = false;    //让整个对象保持同一个socket，不再重新分配
    private $keepSocketServer = array();    //对象保持同一个socket的服务器信息

    function __construct($id = null)
    {
        $key = empty($id) ? 'default' : $id;
        self::$_instances[$key] = $this;
        $this->haveSwoole = extension_loaded('swoole');
        $this->haveSockets = extension_loaded('sockets');
    }

    /**
     * @param bool $keepSocket
     */
    public function setKeepSocket($keepSocket)
    {
        $this->keepSocket = $keepSocket;
    }

    /**
     * 设置编码类型
     * @param $type
     * @param $gzip
     * @throws \Exception
     */
    function setEncodeType($type, $gzip)
    {
        //兼容老版本，老版本true代表用json false代表serialize
        if ($type === true)
        {
            $type = RPCServer::DECODE_JSON;
        }
        if ($type === false)
        {
            $type = RPCServer::DECODE_PHP;
        }
        
        if ($type === RPCServer::DECODE_SWOOLE and (substr(PHP_VERSION, 0, 1) != '7'))
        {
            throw new \Exception("swoole_serialize only use in phpng");
        }
        else
        {
            $this->encode_type = $type;
        }
        if ($gzip)
        {
            $this->encode_gzip = true;
        }
    }

    /**
     * 获取SOA服务实例
     * @param $id
     * @return Server
     */
    static function getInstance($id = null)
    {
        $key = empty($id) ? 'default' : $id;
        if (Core::$enableCoroutine)
        {
            return new static($id);
        }
        if (empty(self::$_instances[$key]))
        {
            $object = new static($id);
        }
        else
        {
            $object = self::$_instances[$key];
        }
        return $object;
    }

    protected function beforeRequest($retObj)
    {

    }

    protected function afterRequest($retObj)
    {

    }

    /**
     * 生成请求串号
     * @return int
     */
    static function getRequestId()
    {
        $us = strstr(microtime(), ' ', true);
        return intval(strval($us * 1000 * 1000) . rand(100, 999));
    }

    protected function closeConnection($host, $port)
    {
        $conn_key = $host . ':' . $port;
        if (!isset($this->connections[$conn_key]))
        {
            return false;
        }
        $socket = $this->connections[$conn_key];
        $socket->close(true);
        unset($this->connections[$conn_key]);
        return true;
    }

    protected function getConnection($host, $port)
    {
        $ret = false;
        $conn_key = $host.':'.$port;
        if (isset($this->connections[$conn_key]))
        {
            return $this->connections[$conn_key];
        }
        //基于Swoole扩展
        if ($this->haveSwoole)
        {
            $socket = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_KEEP, SWOOLE_SOCK_SYNC);
            $socket->set(array(
                'open_length_check' => true,
                'package_max_length' => $this->packet_maxlen,
                'package_length_type' => 'N',
                'package_body_offset' => RPCServer::HEADER_SIZE,
                'package_length_offset' => 0,
            ));
            /**
             * 尝试重连一次
             */
            for ($i = 0; $i < 2; $i++)
            {
                $ret = $socket->connect($host, $port, $this->timeout);
                if ($ret === false and ($socket->errCode == 114 or $socket->errCode == 115))
                {
                    //强制关闭，重连
                    $socket->close(true);
                    continue;
                }
                else
                {
                    break;
                }
            }
        }
        //基于sockets扩展
        elseif ($this->haveSockets)
        {
            $socket = new TCP;
            $socket->try_reconnect = false;
            $ret = $socket->connect($host, $port, $this->timeout);
        }
        //基于stream
        else
        {
            $socket = new Stream();
            $ret = $socket->connect($host, $port, $this->timeout);
        }
        if ($ret)
        {
            $this->connections[$conn_key] = $socket;
            return $socket;
        }
        else
        {
            return false;
        }
    }

    /*
     * 验证缓存中是否存在已经配置为下线的连接 防止短时间内配置恢复后连接错误使用
     */
    protected function validConnection($servers)
    {
        $offline = [];
        foreach ($servers as $k => $svr)
        {
            if (!empty($svr['status']) and $svr['status'] == 'offline')
            {
                $offline[] = $svr;
            }
        }
        if (!empty($offline)) {
            foreach ($offline as $svr)
            {
                $conn_key = $svr['host'].':'.$svr['port'];
                if (isset($this->connections[$conn_key]))
                {
                    $socket = $this->connections[$conn_key];
                    $socket->close(true);
                    unset($this->connections[$conn_key]);
                }
            }
        }
    }

    /**
     * 连接到服务器
     * @param RPC_Result $retObj
     * @return bool
     * @throws \Exception
     */
    protected function connectToServer($retObj)
    {
        $servers = $this->servers;
        //循环连接
        while (count($servers) > 0)
        {
            $svr = $this->getServer($servers);
            if (empty($svr))
            {
                return false;
            }
            $this->validConnection($servers);
            $socket = $this->getConnection($svr['host'], $svr['port']);
            //连接失败，服务器节点不可用
            //TODO 如果连接失败，需要上报机器存活状态
            if ($socket === false)
            {
                foreach($servers as $k => $v)
                {
                    if ($v['host'] == $svr['host'] and $v['port'] == $svr['port'])
                    {
                        //从Server列表中移除
                        unset($servers[$k]);
                    }
                }
                if ($this->keepSocket)
                {
                    //若连接失败，则清除掉该server
                    $this->keepSocketServer = array();
                }
            }
            else
            {
                $retObj->socket = $socket;
                $retObj->server_host = $svr['host'];
                $retObj->server_port = $svr['port'];
                return true;
            }
        }
        return false;
    }

    /**
     * 发送请求
     * @param $send
     * @param RPC_Result $retObj
     * @return bool
     * @throws \Exception
     */
    protected function request($send, $retObj)
    {
        $retObj->send = $send;
        $this->beforeRequest($retObj);

        $retObj->index = $this->requestIndex++;
        connect_to_server:
        if ($this->connectToServer($retObj) === false)
        {
            $retObj->code = RPC_Result::ERR_CONNECT;
            return false;
        }
        //请求串号
        $retObj->requestId = self::getRequestId();
        //打包格式
        $encodeType = $this->encode_type;
        if ($this->encode_gzip)
        {
            $encodeType |= RPCServer::DECODE_GZIP;
        }
        //发送失败了
        if ($retObj->socket->send(RPCServer::encode($retObj->send, $encodeType, 0, $retObj->requestId)) === false)
        {
            $this->closeConnection($retObj->server_host, $retObj->server_port);
            //连接被重置了，重现连接到服务器
            if ($this->haveSwoole and $retObj->socket->errCode == 104)
            {
                goto connect_to_server;
            }
            $retObj->code = RPC_Result::ERR_SEND;
            unset($retObj->socket);
            return false;
        }
        $retObj->code = RPC_Result::ERR_RECV;
        //加入wait_list
        $this->waitList[$retObj->requestId] = $retObj;
        return true;
    }

    /**
     * 设置环境变量
     * @return array
     */
    public function getEnv()
    {
        return $this->env;
    }

    /**
     * 获取环境变量
     * @param array $env
     */
    public function setEnv($env)
    {
        $this->env = $env;
    }

    /**
     * 设置一项环境变量
     * @param $k
     * @param $v
     */
    public function putEnv($k, $v)
    {
        $this->env[$k] = $v;
    }


    /**
     * 设置超时时间，包括连接超时和接收超时
     * @param $timeout
     */
    function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * 设置用户名和密码
     * @param $user
     * @param $password
     */
    function auth($user, $password)
    {
        $this->putEnv('user', $user);
        $this->putEnv('password', sha1($user . '|' . $password));
    }

    /**
     * 完成请求
     * @param $retData
     * @param $retObj RPC_Result
     * @param $header
     */
    protected function finish($retData, $retObj, $header)
    {
        //解包失败了
        if ($retData === false)
        {
            $retObj->code = RPC_Result::ERR_UNPACK;
        }
        //调用成功
        elseif (isset($header['errno']) && $header['errno'] === self::OK)
        {
            $retObj->code = self::OK;
            $retObj->data = $retData;
        }
        //服务器返回失败
        else
        {
            $retObj->code = $header['errno'] ?? RPC_Result::ERR_SERVER;
            if ($retData instanceof Response) {
                $retData = $retData->toArray();
            } elseif (!is_array($retData)) {
                $retData = $retData ? (array) $retData : [];
            }
            $retObj->data = new Response($retObj->code, RPCServer::getErrorMsg($header['errno'] ?? RPC_Result::ERR_SERVER), $retData);
            
        }
        unset($this->waitList[$retObj->requestId]);
        //执行after钩子函数
        $this->afterRequest($retObj);
        //执行回调函数
        if ($retObj->callback)
        {
            call_user_func($retObj->callback, $retObj);
        }
    }

    /**
     * 添加服务器
     * @param array $servers
     */
    function addServers(array $servers)
    {
        if (isset($servers['host']))
        {
            self::formatServerConfig($servers);
            $this->servers[] = $servers;
        }
        else
        {
            //兼容老的写法
            foreach ($servers as $svr)
            {
                // 127.0.0.1:8001 的写法
                if (is_string($svr))
                {
                    list($config['host'], $config['port']) = explode(':', $svr);
                }
                else
                {
                    $config = $svr;
                }
                self::formatServerConfig($config);
                $this->servers[] = $config;
            }
        }
    }

    /**
     * @param $config
     * @throws InvalidParam
     */
    static protected function formatServerConfig(&$config)
    {
        if (empty($config['host']))
        {
            throw new InvalidParam("require 'host' option.");
        }
        if (empty($config['port']))
        {
            throw new InvalidParam("require 'port' option.");
        }
        if (empty($config['status']))
        {
            $config['status'] = 'online';
        }
        if (empty($config['weight']))
        {
            $config['weight'] = 100;
        }
    }

    /**
     * 设置服务器
     * @param array $servers
     */
    function setServers(array $servers)
    {
        foreach($servers as &$svr)
        {
            self::formatServerConfig($svr);
        }
        $this->servers = $servers;
    }

    /**
     * 从配置中取出一个服务器配置
     * @param $servers array
     * @return array
     * @throws \Exception
     */
    function getServer($servers)
    {
        if (empty($servers))
        {
            throw new \Exception("servers config empty.");
        }

        if ($this->keepSocket)
        {
            if (is_array($this->keepSocketServer) && count($this->keepSocketServer))
            {
                return $this->keepSocketServer;
            }
            else
            {
                $this->keepSocketServer = Tool::getServer($servers);

                return $this->keepSocketServer;
            }
        }

        //保留老的server获取方式
        return Tool::getServer($servers);
    }

    /**
    /**
     * RPC调用
     *
     * @param $function
     * @param $params
     * @param $callback
     * @return RPC_Result
     */
    function task($function, $params = array(), $callback = null)
    {
        $retObj = new RPC_Result($this);
        $send = array('call' => $function, 'params' => $params);
        if (count($this->env) > 0)
        {
            //调用端环境变量
            $send['env'] = $this->env;
        }
        $this->request($send, $retObj);
        $retObj->callback = $callback;
        return $retObj;
    }

    /**
     * 侦测服务器是否存活
     */
    function ping()
    {
        return $this->task('PING')->getResult() === 'PONG';
    }

    /**
     * @param $connection
     * @return bool|string
     */
    protected function recvPacket($connection, $timeout=0.5)
    {
        if ($this->haveSwoole)
        {
            return $connection->recv();
        }

        /**
         * Stream or Socket
         */
        $_header_data = $connection->recv(RPCServer::HEADER_SIZE, true);
        if (empty($_header_data))
        {
            return "";
        }
        //这里仅使用了length和type，uid,serid未使用
        $header = unpack(RPCServer::HEADER_STRUCT, $_header_data);
        //错误的包头，返回空字符串，结束连接
        if ($header === false or $header['length'] <= 0 or $header['length'] > $this->packet_maxlen)
        {
            return "";
        }

        $_body_data = $connection->recv($header['length'], true);
        if (empty($_body_data))
        {
            return "";
        }
        return $_header_data . $_body_data;
    }

    /**
     * select等待数据接收事件
     * @param $read
     * @param $write
     * @param $error
     * @param $timeout
     * @return int
     */
    protected function select($read, $write, $error, $timeout)
    {
        if ($this->haveSwoole)
        {
            return swoole_client_select($read, $write, $error, $timeout);
        }

        $t_sec = (int)$timeout;
        $t_usec = (int)(($timeout - $t_sec) * 1000 * 1000);

        foreach ($read as $o)
        {
            $_read[] = $o->getSocket();
        }
        foreach ($write as $o)
        {
            $_write[] = $o->getSocket();
        }
        foreach ($error as $o)
        {
            $_error[] = $o->getSocket();
        }

        if ($this->haveSockets)
        {
            return socket_select($_read, $_write, $_error, $t_sec, $t_usec);
        }
        else
        {
            return stream_select($_read, $_write, $_error, $t_sec, $t_usec);
        }
    }

    protected function freeConnection($socket)
    {

    }

    /**
     * 接收响应
     * @param $timeout
     * @return int
     */
    function wait($timeout = 0.5)
    {
        $st = microtime(true);
        $success_num = 0;

        while (count($this->waitList) > 0)
        {
            $write = $error = $read = array();
            foreach ($this->waitList as $obj)
            {
                /**
                 * @var $obj RPC_Result
                 */
                if ($obj->socket !== null)
                {
                    $read[] = $obj->socket;
                }
            }
            if (empty($read))
            {
                break;
            }
            //去掉重复的socket
            Tool::arrayUnique($read);
            //等待可读事件
            $n = $this->select($read, $write, $error, $timeout);
            if ($n > 0)
            {
                //可读
                foreach($read as $connection)
                {
                    $data = $this->recvPacket($connection,$timeout);
                    //socket被关闭了
                    if ($data === "")
                    {
                        foreach($this->waitList as $retObj)
                        {
                            if ($retObj->socket == $connection)
                            {
                                $retObj->code = RPC_Result::ERR_CLOSED;
                                unset($this->waitList[$retObj->requestId]);
                                $this->closeConnection($retObj->server_host, $retObj->server_port);
                                //执行after钩子函数
                                $this->afterRequest($retObj);
                            }
                        }
                        continue;
                    }
                    elseif ($data === false)
                    {
                        continue;
                    }
                    $header = unpack(RPCServer::HEADER_STRUCT, substr($data, 0, RPCServer::HEADER_SIZE));
                    //不在请求列表中，错误的请求串号
                    if (!isset($this->waitList[$header['serid']]))
                    {
                        trigger_error(__CLASS__ . " invalid responseId[{$header['serid']}].", E_USER_WARNING);
                        continue;
                    }
                    $retObj = $this->waitList[$header['serid']];
                    //成功处理
                    $this->finish(RPCServer::decode(substr($data, RPCServer::HEADER_SIZE), $header['type']), $retObj, $header);
                    $success_num++;
                }
            }
            //发生超时
            if ((microtime(true) - $st) > $timeout)
            {
                foreach ($this->waitList as $obj)
                {
                    $obj->code = ($obj->socket->isConnected()) ? RPC_Result::ERR_TIMEOUT : RPC_Result::ERR_CONNECT;
                    $this->closeConnection($obj->server_host, $obj->server_port);
                    //执行after钩子函数
                    $this->afterRequest($obj);
                }
                //清空当前列表
                $this->waitList = array();
                foreach($read as $r)
                {
                    $this->freeConnection($r);
                }
                return $success_num;
            }
        }

        foreach($read as $r)
        {
            $this->freeConnection($r);
        }

        //未发生任何超时
        $this->waitList = array();
        $this->requestIndex = 0;
        return $success_num;
    }

    /**
     * 关闭所有连接
     */
    function close()
    {
        foreach ($this->connections as $key => $socket)
        {
            /**
             * @var $socket \swoole_client
             */
            $socket->close(true);
            unset($this->connections[$key]);
        }
    }
}

/**
 * SOA服务请求结果对象
 * @package SPF\Client
 */
class RPC_Result
{
    public $code = self::ERR_NO_READY;
    public $msg;
    public $data = null;
    public $send;  //要发送的数据
    public $type;
    public $index;

    /**
     * 请求串号
     */
    public $requestId;

    /**
     * 回调函数
     * @var mixed
     */
    public $callback;

    /**
     * @var \SPF\Client\TCP
     */
    public $socket = null;

    /**
     * SOA服务器的IP地址
     * @var string
     */
    public $server_host;

    /**
     * SOA服务器的端口
     * @var int
     */
    public $server_port;

    /**
     * @var RPC
     */
    protected $soa_client;

    const ERR_NO_READY   = 8001; //未就绪
    const ERR_CONNECT    = 8002; //连接服务器失败
    const ERR_TIMEOUT    = 8003; //服务器端超时
    const ERR_SEND       = 8004; //发送失败

    const ERR_SERVER     = 8005; //server返回了错误码
    const ERR_UNPACK     = 8006; //解包失败了

    const ERR_HEADER     = 8007; //错误的协议头
    const ERR_TOOBIG     = 8008; //超过最大允许的长度
    const ERR_CLOSED     = 8009; //连接被关闭

    const ERR_RECV       = 8010; //等待接收数据

    function __construct($soa_client)
    {
        $this->soa_client = $soa_client;
    }

    function getResult($timeout = 0.5)
    {
        if ($this->code == self::ERR_RECV)
        {
            $this->soa_client->wait($timeout);
        }
        return $this->data;
    }
}

class SOA_Result extends RPC_Result
{

}
