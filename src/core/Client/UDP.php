<?php
namespace SPF\Client;

/**
 * UDP客户端
 * @author hantianfeng
 */
class UDP extends Socket
{
    public $remote_host;
    public $remote_port;

    /**
     * 连接到服务器
     * 接受一个浮点型数字作为超时，整数部分作为sec，小数部分*100万作为usec
     * @param string $host 服务器地址
     * @param int $port 服务器地址
     * @param float $timeout 超时默认值，连接，发送，接收都使用此设置
     * @param bool $udp_connect 是否启用connect方式
     * @return bool
     */
    public function connect($host, $port, $timeout = 0.1, $udp_connect = true)
    {
        //判断超时为0或负数
        if (empty($host) or empty($port) or $timeout <= 0) {
            $this->errCode = -10001;
            $this->errMsg = "param error";
            return false;
        }
        $this->host = $host;
        $this->port = $port;
        $this->sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->set_timeout($timeout, $timeout);
        //$this->set_bufsize($this->sendbuf_size, $this->recvbuf_size);

        //是否用UDP Connect
        if ($udp_connect !== true) {
            return true;
        }
        if (socket_connect($this->sock, $host, $port)) {
            //清理connect前的buffer数据遗留
            while (@socket_recv($this->sock, $buf, 65535, MSG_DONTWAIT)) ;
            return true;
        } else {
            $this->set_error();
            return false;
        }
    }

    /**
     * 发送数据
     * @param string $data
     * @return $n or false
     */
    public function send($data)
    {
        $len = strlen($data);
        $n = socket_sendto($this->sock, $data, $len, 0, $this->host, $this->port);

        if ($n === false or $n < $len) {
            $this->set_error();
            return false;
        } else {
            return $n;
        }
    }

    /**
     * 接收数据，UD包不能分2次读，recv后会清除数据包，所以必须要一次性读完
     *
     * @param int $length 接收数据的长度
     * @param bool $waitall 等待接收到全部数据后再返回，注意waitall=true,超过包长度会阻塞住
     */
    public function recv($length = 65535, $waitall = 0)
    {
        if ($waitall) {
            $waitall = MSG_WAITALL;
        }
        $ret = socket_recvfrom($this->sock, $data, $length, $waitall, $this->remote_host, $this->remote_port);
        if ($ret === false) {
            $this->set_error();
            //重试一次，这里为防止意外，不使用递归循环
            if ($this->errCode == 4) {
                socket_recvfrom($this->sock, $data, $length, $waitall, $this->remote_host, $this->remote_port);
            } else {
                return false;
            }
        }
        return $data;
    }

    /**
     * 关闭socket连接
     */
    public function close()
    {
        if ($this->sock) {
            socket_close($this->sock);
        }
        $this->sock = null;
    }
}
