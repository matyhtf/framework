<?php
namespace SPF\Protocol;

use SPF;
use SPF\Coroutine\BaseContext as Context;
/**
 * 协议基类，实现一些公用的方法
 * @package SPF\Protocol
 */
abstract class Base implements SPF\IFace\Protocol
{
    const ERR_SUCCESS           = 0;      //成功

    const ERR_HEADER            = 9001;   //错误的包头
    const ERR_TOOBIG            = 9002;   //请求包体长度超过允许的范围
    const ERR_SERVER_BUSY       = 9003;   //服务器繁忙，超过处理能力

    const ERR_UNPACK            = 9204;   //解包失败
    const ERR_PARAMS            = 9205;   //参数错误
    const ERR_NOFUNC            = 9206;   //函数不存在
    const ERR_CALL              = 9207;   //执行错误
    const ERR_ACCESS_DENY       = 9208;   //访问被拒绝，客户端主机未被授权
    const ERR_USER              = 9209;   //用户名密码错误
    const ERR_SEND              = 9301;   //发送客户端失败


    static $errMsg = [
        0 => 'success',

        9001 => '错误的包头',
        9002 => '请求包体长度超过允许的范围',
        9003 => '服务器繁忙',

        9204 => '解包失败',
        9205 => '参数错误',
        9206 => '函数不存在',
        9207 => '执行错误',
        9208 => '访问被拒绝，客户端主机未被授权',
        9209 => '用户名密码错误',

        9301 => '发送客户端失败',
    ];
    public $default_port;
    public $default_host;
    /**
     * @var \SPF\IFace\Log
     */
    public $log;

    /**
     * @var \SPF\Server
     */
    public $server;

    /**
     * @var array
     */
    protected $clients;

    /**
     * @var integer
     */
    protected static $errorCode = 0;

    /**
     * @param $errorCode
     */
    static function setErrorCode($errorCode)
    {
        if (SPF\App::$enableCoroutine)
        {
            Context::put("rpc_error_code", $errorCode);
        }
        else
        {
            self::$errorCode = $errorCode;
        }
    }

    /**
     * @return int
     */
    static function getErrorCode()
    {
        if (SPF\App::$enableCoroutine)
        {
            return Context::get("rpc_error_code");
        }
        else
        {
            return self::$errorCode;
        }
    }

    static function reSetError()
    {
        if (SPF\App::$enableCoroutine)
        {
            Context::delete("rpc_error_code");
        }
        else
        {
            self::$errorCode = 0;
        }
    }

    static function getErrorMsg($errorCode)
    {
        return isset(self::$errMsg[$errorCode]) ? self::$errMsg[$errorCode] : "";
    }

    /**
     * 设置Logger
     * @param $log
     */
    function setLogger($log)
    {
        $this->log = $log;
    }

    function run($array)
    {
        SPF\Error::$echo_html = true;
        $this->server->run($array);
    }

    function daemonize()
    {
        $this->server->daemonize();
    }

    /**
     * 打印Log信息
     * @param $msg
     * @param string $type
     */
    function log($msg)
    {
        $this->log->info($msg);
    }

    function task($task, $dstWorkerId = -1, $callback = null)
    {
        $this->server->task($task, $dstWorkerId = -1, $callback);
    }

    function onStart($server)
    {

    }

    function onConnect($server, $client_id, $from_id)
    {

    }

    function onClose($server, $client_id, $from_id)
    {

    }

    function onShutdown($server)
    {

    }
}
