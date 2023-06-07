<?php
namespace SPF\Protocol;

use SPF;
use SPF\Exception\ValidateException;
use SPF\Coroutine\BaseContext as Context;
use SPF\Struct\Response;

/**
 * RPC服务器
 * @package SPF\Network
 * @method beforeRequest
 * @method afterRequest
 */
class RPCServer extends Base implements SPF\IFace\Protocol
{
    /**
     * 版本号
     */
    const VERSION = 1005;

    const HEADER_SIZE = 32;
    const HEADER_STRUCT = "Nlength/Ntype/Nuid/Nserid/Nerrno/Nreserve1/Nreserve2/Nreserve3";
    const HEADER_PACK = "NNNNNNNN";//4*8=32

    const DECODE_PHP = 1;   //使用PHP的serialize打包
    const DECODE_JSON = 2;   //使用json_encode打包
    const DECODE_MSGPACK = 3;   //使用msgpack打包
    const DECODE_SWOOLE = 4;   //使用swoole_serialize打包
    const DECODE_GZIP = 128; //启用GZIP压缩

    const ALLOW_IP = 1;
    const ALLOW_USER = 2;

    /**
     * 客户端环境变量
     * @var array
     */
    public static $clientEnv;
    /**
     * 请求头
     * @var array
     */
    public static $requestHeader;
    public static $clientEnvKey = "client_env";
    public static $requestHeaderKey = "request_header";
    public static $stop = false;

    public $packet_maxlen = 2465792; //2M默认最大长度

    protected $_headers = array(); //保存头
    protected $appNamespaces = array(); //应用程序命名空间
    protected $ipWhiteList = array(); //IP白名单
    protected $userList = array(); //用户列表
    protected $verifyIp = false;
    protected $verifyUser = false;

    public function onWorkerStop($serv, $worker_id)
    {
        $this->log("Worker[$worker_id] is stop");
    }

    public function onShutdown($server)
    {
        $this->log("Worker is stop");
        SPF\App::getInstance()->log->flush();
    }

    public function onTimer($serv, $interval)
    {
        $this->log("Timer[$interval] call");
    }

    public function onReceive($serv, $fd, $reactor_id, $data)
    {
        //解析包头
        $header = unpack(self::HEADER_STRUCT, substr($data, 0, self::HEADER_SIZE));
        //错误的包头 设置错误码 关闭连接
        if ($header === false) {
            self::setErrorCode(self::ERR_HEADER);
            return $this->close($fd);
        }
        $header['fd'] = $fd;
        $this->_headers[$fd] = $header;
        //长度错误
        if ($header['length'] - self::HEADER_SIZE > $this->packet_maxlen or strlen($data) > $this->packet_maxlen) {
            self::setErrorCode(self::ERR_TOOBIG);
            return $this->sendErrorMessage($fd, self::ERR_TOOBIG);
        }
        //数据解包
        $request = self::decode(substr($data, self::HEADER_SIZE), $this->_headers[$fd]['type']);
        if ($request === false) {
            self::setErrorCode(self::ERR_UNPACK);
            $this->sendErrorMessage($fd, self::ERR_UNPACK);
        } //执行远程调用
        else {
            //当前请求的头
            $_header = $this->_headers[$fd];
            self::setRequestHeader($_header);
            //调用端环境变量

            self::$clientEnv = null;//reset env
            if (!empty($request['env'])) {
                self::setClientEnv($request['env']);
            }
            //socket信息
            self::$clientEnv['_socket'] = $this->server->connection_info($_header['fd']);
            $response = $this->call($request, $_header);
            $response = $response !== false ? $response : '';
            //发送响应
            $ret = $this->server->send($fd, self::encode(
                $response,
                $_header['type'],
                $_header['uid'],
                $_header['serid'],
                self::getErrorCode()
            ));
            if ($ret === false) {
                trigger_error(
                    "SendToClient failed. code=" . $this->server->getLastError() . " params="
                    . var_export($request, true) . "\nheaders=" . var_export($_header, true),
                    E_USER_WARNING
                );
            }
            //退出进程
            if (self::$stop) {
                exit(0);
            }
        }
        self::clean();
        return true;
    }

    public static function clean()
    {
        if (SPF\App::$enableCoroutine) {
            Context::del(self::$requestHeaderKey);
            Context::del(self::$clientEnvKey);
        } else {
            self::$clientEnv = null;
            self::$requestHeader = null;
        }
        self::reSetError();
    }

    public static function setClientEnv($env)
    {
        if (SPF\App::$enableCoroutine) {
            Context::put(self::$clientEnvKey, $env);
        } else {
            self::$clientEnv = $env;
        }
    }

    /**
     * 获取客户端环境信息
     * @return array
     */
    public static function getClientEnv()
    {
        if (SPF\App::$enableCoroutine) {
            return Context::get(self::$clientEnvKey);
        } else {
            return self::$clientEnv;
        }
    }

    public static function setRequestHeader($header)
    {
        if (SPF\App::$enableCoroutine) {
            Context::put(self::$requestHeaderKey, $header);
        } else {
            self::$requestHeader = $header;
        }
    }

    /**
     * 获取请求头信息，包括UID、Serid串号等
     * @return array
     */
    public static function getRequestHeader()
    {
        if (SPF\App::$enableCoroutine) {
            return Context::get(self::$requestHeaderKey);
        } else {
            return self::$requestHeader;
        }
    }



    public function sendErrorMessage($fd, $errno)
    {
        return $this->server->send($fd, self::encode(array('errno' => $errno), $this->_headers[$fd]['type']));
    }

    /**
     * 打包数据
     * @param $data
     * @param int $type
     * @param int $uid
     * @param int $serid
     * @param int $error    服务端错误码
     * @param int $reserve1 保留字段
     * @param int $reserve2
     * @param int $reserve3
     * @return string
     */
    public static function encode(
        $data,
        $type = self::DECODE_PHP,
        $uid = 0,
        $serid = 0,
        $error = 0,
        $reserve1 = 0,
        $reserve2 = 0,
        $reserve3 = 0
    )
    {
        //启用压缩
        if ($type & self::DECODE_GZIP) {
            $_type = $type & ~self::DECODE_GZIP;
            $gzip_compress = true;
        } else {
            $gzip_compress = false;
            $_type = $type;
        }
        switch ($_type) {
            case self::DECODE_JSON:
                $body = json_encode($data);
                break;
            case self::DECODE_SWOOLE:
                $body = \swoole_serialize::pack($data);
                break;
            case self::DECODE_PHP:
            default:
                $body = serialize($data);
                break;
        }
        if ($gzip_compress) {
            $body = gzencode($body);
        }
        return pack(
            RPCServer::HEADER_PACK,
            strlen($body),
            $type,
            $uid,
            $serid,
            $error,
            $reserve1,
            $reserve2,
            $reserve3
        ) . $body;
    }

    /**
     * 解包数据
     * @param string $data
     * @param int $unseralize_type
     * @return string
     */
    public static function decode($data, $unseralize_type = self::DECODE_PHP)
    {
        if ($unseralize_type & self::DECODE_GZIP) {
            $unseralize_type &= ~self::DECODE_GZIP;
            $data = gzdecode($data);
        }
        switch ($unseralize_type) {
            case self::DECODE_JSON:
                return json_decode($data, true);
            case self::DECODE_SWOOLE:
                return \swoole_serialize::unpack($data);
            case self::DECODE_PHP:
            default:
                return unserialize($data);
        }
    }

    /**
     * @param $serv
     * @param int $fd
     * @param $from_id
     */
    public function onClose($serv, $fd, $from_id)
    {
    }

    /**
     * 增加命名空间
     * @param $name
     * @param $path
     *
     * @throws \Exception
     */
    public function addNameSpace($name, $path)
    {
        if (!is_dir($path)) {
            throw new \Exception("$path is not real path.");
        }
        SPF\App::getInstance()->loader->addNameSpace($name, $path);
    }

    /**
     * 验证IP
     * @param $ip
     * @return bool
     */
    protected function verifyIp($ip)
    {
        return isset($this->ipWhiteList[$ip]);
    }

    /**
     * 验证用户名密码
     * @param $user
     * @param $password
     * @return bool
     */
    protected function verifyUser($user, $password)
    {
        if (!isset($this->userList[$user])) {
            return false;
        }
        if ($this->userList[$user] != sha1($user . '|' . $password)) {
            return false;
        }
        return true;
    }

    /**
     * 调用远程函数
     *
     * @param $request
     * @param $header
     * @return mixed|string|SPF\Struct\Response
     */
    protected function call($request, $header)
    {
        if (empty($request['call'])) {
            self::setErrorCode(self::ERR_PARAMS);
            return false;
        }
        /**
         * 侦测服务器是否存活
         */
        if ($request['call'] === 'PING') {
            return 'PONG';
        }
        //验证客户端IP是否被允许访问
        if ($this->verifyIp) {
            if (!$this->verifyIp(self::$clientEnv['_socket']['remote_ip'])) {
                self::setErrorCode(self::ERR_ACCESS_DENY);
                return false;
            }
        }
        //验证密码是否正确
        if ($this->verifyUser) {
            if (empty(self::$clientEnv['user']) or empty(self::$clientEnv['password'])) {
                fail:
                self::setErrorCode(self::ERR_USER);
                return false;
            }
            if (!$this->verifyUser(self::$clientEnv['user'], self::$clientEnv['password'])) {
                goto fail;
            }
        }
        //函数不存在
        if (!is_callable($request['call'])) {
            self::setErrorCode(self::ERR_NOFUNC);
            return false;
        }
        //前置方法
        if (method_exists($this, 'beforeRequest')) {
            try {
                $request = $this->beforeRequest($request, $header);
            } catch (ValidateException $e) {
                self::setErrorCode(self::ERR_PARAMS);
                return $e->getErrors();
            }
        }
        try {
            //调用接口方法
            $ret = call_user_func_array($request['call'], $request['params']);
        } catch (\Throwable $e) {
            self::setErrorCode(self::ERR_CALL);
            return false;
        }

        //后置方法
        if (method_exists($this, 'afterRequest')) {
            $this->afterRequest($ret);
        }
        //禁止接口返回NULL，客户端得到NULL时认为RPC调用失败
        if ($ret === null) {
            self::setErrorCode(self::ERR_CALL);
            return false;
        }
        self::setErrorCode(self::ERR_SUCCESS);
        return $ret;
    }

    /**
     * 关闭连接
     * @param $fd
     */
    protected function close($fd)
    {
        $this->server->close($fd);
    }

    /**
     * 添加访问规则
     * @param $ip
     * @throws SPF\Exception\InvalidParam
     */
    public function addAllowIP($ip)
    {
        if (SPF\Validate::ip($ip)) {
            $this->ipWhiteList[$ip] = true;
            $this->verifyIp = true;
        } else {
            throw new SPF\Exception\InvalidParam("require ip address.");
        }
    }

    /**
     * 添加用户许可
     * @param $user
     * @param $password
     */
    public function addAllowUser($user, $password)
    {
        $this->userList[$user] = $password;
        $this->verifyUser = true;
    }
}
