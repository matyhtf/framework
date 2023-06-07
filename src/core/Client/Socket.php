<?php
namespace SPF\Client;

/**

 */

/**
 * Example:
 * -----------------------------------------------------------------------------------------------
 * $client = new SPF\Client\TCP; //SPF\Client\UDP or SPF\Client\TCP
 * if($client->connect('127.0.0.1', 80, 0.5)) //Host,Port,Timeout
 * {
 *     $client->send("GET / HTTP/1.1\r\n\r\n");
 *     echo $client->recv();
 * }
 * else
 * {
 *     echo $client->code;
 *     echo $client->msg;
 * }
 * 网络客户端封装基类
 */
abstract class Socket
{
    /**
     * @var resource
     */
    protected $sock;
    protected $timeout_send;
    protected $timeout_recv;
    public $sendbuf_size = 65535;
    public $recvbuf_size = 65535;

    public $errCode = 0;
    public $errMsg = '';
    public $host; //Server Host
    public $port; //Server Port

    const ERR_RECV_TIMEOUT = 11; //接收数据超时，server端在规定的时间内没回包
    const ERR_INPROGRESS = 115; //正在处理中

    /**
     * 错误信息赋值
     */
    protected function set_error()
    {
        $this->errCode = socket_last_error($this->sock);
        $this->errMsg = socket_strerror($this->errCode);
        socket_clear_error($this->sock);
    }

    /**
     * 设置超时
     * @param float $recv_timeout 接收超时
     * @param float $send_timeout 发送超时
     */
    public function set_timeout($timeout_recv, $timeout_send)
    {
        $_timeout_recv_sec = (int)$timeout_recv;
        $_timeout_send_sec = (int)$timeout_send;

        $this->timeout_recv = $timeout_recv;
        $this->timeout_send = $timeout_send;

        $_timeout_recv = array(
            'sec' => $_timeout_recv_sec,
            'usec' => (int)(($timeout_recv - $_timeout_recv_sec) * 1000 * 1000)
        );
        $_timeout_send = array(
            'sec' => $_timeout_send_sec,
            'usec' => (int)(($timeout_send - $_timeout_send_sec) * 1000 * 1000)
        );

        $this->setopt(SO_RCVTIMEO, $_timeout_recv);
        $this->setopt(SO_SNDTIMEO, $_timeout_send);
    }

    /**
     * 设置socket参数
     */
    public function setopt($opt, $set)
    {
        socket_set_option($this->sock, SOL_SOCKET, $opt, $set);
    }

    /**
     * 获取socket参数
     */
    public function getopt($opt)
    {
        return socket_get_option($this->sock, SOL_SOCKET, $opt);
    }

    public function getSocket()
    {
        return $this->sock;
    }

    /**
     * 设置buffer区
     * @param $sendbuf_size
     * @param $recvbuf_size
     */
    public function set_bufsize($sendbuf_size, $recvbuf_size)
    {
        $this->setopt(SO_SNDBUF, $sendbuf_size);
        $this->setopt(SO_RCVBUF, $recvbuf_size);
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }
}
