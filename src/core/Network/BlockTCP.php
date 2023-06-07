<?php
namespace SPF\Network;

class BlockTCP extends \SPF\Server\Base
{
    public $server_sock;
    public $server_socket_id;
    public $client_sock;
    public $buffer_size = 8192;
    public $timeout_micro = 1000;

    public function __construct($host, $port, $timeout=30)
    {
        parent::__construct($host, $port, $timeout);
    }
    /**
     * 向client发送数据
     * @param $client_id
     * @param $data
     * @return unknown_type
     */
    public function send($client_id, $data)
    {
        $this->sendData($this->client_sock[$client_id], $data);
    }

    public function shutdown()
    {
        //关闭所有客户端
        foreach ($this->client_sock as $k=>$sock) {
            Stream::close($sock, $this->client_event[$k]);
        }
        //关闭服务器端
        Stream::close($this->server_sock, $this->server_event);
        $this->protocol->onShutdown($this);
    }

    public function close($client_id)
    {
        Stream::close($this->client_sock[$client_id]);
        $this->client_sock[$client_id] = null;
        unset($this->client_sock[$client_id]);
        $this->protocol->onClose($this, $client_id, 0);
    }

    public function server_loop()
    {
        while ($this->client_sock[0] = stream_socket_accept($this->server_sock, -1)) {
            stream_set_blocking($this->client_sock[0], 1);
            if (feof($this->client_sock[0])) {
                $this->close(0);
            }

            //堵塞Server必须读完全部数据
            $data = Stream::read($this->client_sock[0], $this->buffer_size);
            $this->protocol->onReceive($this, 0, 0, $data);
        }
    }

    public function connection_info($fd)
    {
        $peername = stream_socket_get_name($this->client_sock[$fd], true);
        list($ip, $port) = explode(':', $peername);
        return array('remote_port' => $port, 'remote_ip' => $ip);
    }

    public function run($setting)
    {
        //建立服务器端Socket
        $this->server_sock = $this->create("tcp://{$this->host}:{$this->port}");
        stream_set_timeout($this->server_sock, $this->timeout);
        $this->server_socket_id = (int)$this->server_sock;
        stream_set_blocking($this->server_sock, 1);

        $this->spawn($setting);
        $this->protocol->onStart($this);
        $this->server_loop();
    }
}
