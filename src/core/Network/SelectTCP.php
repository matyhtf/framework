<?php
namespace SPF\Network;

class SelectTCP extends \SPF\Server\Base
{
    public $server_socket_id;

    //客户端数量
    public $client_num = 0;

    public function __construct($host, $port, $timeout = 30)
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
        return $this->sendData($this->client_sock[$client_id], $data);
    }

    /**
     * 向所有client发送数据
     * @return unknown_type
     */
    public function sendAll($client_id = null, $data)
    {
        foreach ($this->client_sock as $k => $sock) {
            if ($client_id and $k == $client_id) {
                continue;
            }
            $this->sendData($sock, $data);
        }
    }

    public function shutdown()
    {
        //关闭所有客户端
        foreach ($this->client_sock as $k => $sock) {
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
        $this->fds[$client_id] = null;
        unset($this->client_sock[$client_id], $this->fds[$client_id]);
        $this->protocol->onClose($this, $client_id, 0);
        $this->client_num--;
    }

    public function server_loop()
    {
        while (true) {
            $read_fds = $this->fds;
            $write = $exp = null;
            if (stream_select($read_fds, $write, $exp, null)) {
                foreach ($read_fds as $socket) {
                    $socket_id = (int)$socket;
                    if ($socket_id == $this->server_socket_id) {
                        if ($client_socket_id = parent::accept()) {
                            $this->fds[$client_socket_id] = $this->client_sock[$client_socket_id];
                            $this->protocol->onConnect($this, $client_socket_id, 0);
                        }
                    } else {
                        $data = Stream::read($socket, $this->buffer_size);
                        if (!empty($data)) {
                            $this->protocol->onReceive($this, $socket_id, 0, $data);
                        } else {
                            $this->close($socket_id);
                        }
                    }
                }
            }
        }
    }

    public function run($setting = array())
    {
        //建立服务器端Socket
        $this->server_sock = $this->create("tcp://{$this->host}:{$this->port}");
        $this->server_socket_id = (int)$this->server_sock;
        $this->fds[$this->server_socket_id] = $this->server_sock;
        stream_set_blocking($this->server_sock, 0);
        $this->spawn($setting);
        $this->protocol->onStart($this);
        $this->server_loop();
    }
}
