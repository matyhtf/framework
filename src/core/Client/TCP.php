<?php
namespace SPF\Client;

/**
 * TCP客户端
 * @author hantianfeng
 */
class TCP extends Socket
{
    /**
     * 是否重新连接
     */
    public $try_reconnect = true;
    public $connected = false; //是否已连接

    /**
     * 发送数据
     * @param string $data
     * @return bool | int
     */
    function send($data)
    {
        $length = strlen($data);
        $written = 0;
        $t1 = microtime(true);
        //总超时，for循环中计时
        while ($written < $length)
        {
            $n = socket_send($this->sock, substr($data, $written), $length - $written, null);
            //超过总时间
            if (microtime(true) > $this->timeout_send + $t1)
            {
                return false;
            }
            if ($n === false)
            {
                $errno = socket_last_error($this->sock);
                //判断错误信息，EAGAIN EINTR，重写一次
                if ($errno == 11 or $errno == 4)
                {
                    continue;
                }
                else
                {
                    return false;
                }
            }
            $written += $n;
        }
        return $written;
    }

    /**
     * 接收数据
     * @param int $length 接收数据的长度
     * @param bool $waitall 等待接收到全部数据后再返回，注意这里超过包长度会阻塞住
     * @return string | bool
     */
    function recv($length = 65535, $waitall = false)
    {
        $flags = 0;
        if ($waitall)
        {
            $flags = MSG_WAITALL;
        }

        $ret = socket_recv($this->sock, $data, $length, $flags);
        if ($ret === false)
        {
            $this->set_error();
            //重试一次，这里为防止意外，不使用递归循环
            if ($this->errCode == 4)
            {
                socket_recv($this->sock, $data, $length, $waitall);
            }
            else
            {
                return false;
            }
        }
        return $data;
    }

    /**
     * 连接到服务器
     * 接受一个浮点型数字作为超时，整数部分作为sec，小数部分*100万作为usec
     *
     * @param string $host 服务器地址
     * @param int $port 服务器地址
     * @param float $timeout 超时默认值，连接，发送，接收都使用此设置
     * @return bool
     */
    function connect($host, $port, $timeout = 0.1, $nonblock = false)
    {
        //判断超时为0或负数
        if (empty($host) or empty($port) or $timeout <= 0)
        {
            $this->errCode = -10001;
            $this->errMsg = "param error";
            return false;
        }
        $this->host = $host;
        $this->port = $port;
        //创建socket
        $this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->sock === false)
        {
            $this->set_error();
            return false;
        }
        //设置connect超时
        $this->set_timeout($timeout, $timeout);
        $this->setopt(SO_REUSEADDR, 1);
        //非阻塞模式下connect将立即返回
        if ($nonblock)
        {
            socket_set_nonblock($this->sock);
            @socket_connect($this->sock, $this->host, $this->port);
            return true;
        }
        else
        {
            //这里的错误信息没有任何意义，所以屏蔽掉
            if (@socket_connect($this->sock, $this->host, $this->port))
            {
                $this->connected = true;
                return true;
            }
            elseif ($this->try_reconnect)
            {
                if (@socket_connect($this->sock, $this->host, $this->port))
                {
                    $this->connected = true;
                    return true;
                }
            }
        }
        $this->set_error();
        trigger_error("connect server[{$this->host}:{$this->port}] fail. Error: {$this->errMsg}[{$this->errCode}].");
        return false;
    }

    /**
     * 关闭socket连接
     */
    function close()
    {
        if ($this->sock)
        {
            socket_close($this->sock);
        }
        $this->sock = null;
    }

    /**
     * 是否连接到服务器
     * @return bool
     */
    function isConnected()
    {
        return $this->connected;
    }
}
