<?php
namespace SPF\Network;

class SelectUDP extends \SPF\Server\Base implements \SPF\UDP_Server_Driver
{
    public $server_block = 0;

    public function __construct($host, $port, $timeout = 30)
    {
        parent::__construct($host, $port, $timeout = 30);
    }

    public function server_loop()
    {
        while (true) {
            $read_fds = array($this->server_sock);
            if (stream_select($read_fds, $write = null, $exp = null, null)) {
                $data = '';
                while (true) {
                    $buf = stream_socket_recvfrom($this->server_sock, $this->buffer_size, 0, $peer);
                    $data .= $buf;
                    if ($buf===null or strlen($buf)<$this->buffer_size) {
                        break;
                    }
                }
                $this->protocol->onData($peer, $data);
            }
        }
    }
    /**
     * 运行服务器程序
     * @return unknown_type
     */
    public function run($num=1)
    {
        //初始化事件系统
        if (!($this->protocol instanceof Swoole_UDP_Server_Protocol)) {
            return error(902);
        }
        //建立服务器端Socket
        $this->server_sock = $this->create("udp://{$this->host}:{$this->port}");
        stream_set_blocking($this->server_sock, $this->server_block);
        $this->server_socket_id = (int)$this->server_sock;
        //设置事件监听，监听到服务器端socket可读，则有连接请求
        $this->protocol->onStart();
        $this->server_loop();
    }

    /**
     * 关闭服务器程序
     */
    public function shutdown()
    {
        //关闭服务器端
        \SPF\Network\Stream::close($this->server_sock);
        //关闭事件循环
        $this->protocol->onShutdown($this);
    }
}
