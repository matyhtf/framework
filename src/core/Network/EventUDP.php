<?php
namespace SPF\Network;
class EventUDP extends \SPF\Server\Base implements \SPF\UDP_Server_Driver
{
    /**
     * Server Socket
     * @var unknown_type
     */
	public $server_sock;
	//最大连接数
	public $max_connect=1000;

    //客户端socket列表
	public $client_sock = array();
	//客户端数量
	public $client_num = 0;
	public $buffer_size = 1028;
	public $flags = STREAM_OOB;

	function __construct($host,$port,$timeout=30)
	{
		parent::__construct($host,$port,$timeout=30);
	}
    /**
     * 运行服务器程序
     * @return unknown_type
     */
	function run()
	{
	    //初始化事件系统
		$this->init();
		//建立服务器端Socket
		$this->server_sock = $this->create("udp://{$this->host}:{$this->port}");

		//设置事件监听，监听到服务器端socket可读，则有连接请求
		event_set($this->server_event,$this->server_sock, EV_READ | EV_PERSIST, "sw_server_handle_recvfrom",$this);
		event_base_set($this->server_event,$this->base_event);
		event_add($this->server_event);
		$this->protocol->onStart();
		event_base_loop($this->base_event);
	}
	/**
	 * 关闭服务器程序
	 * @return unknown_type
	 */
	function shutdown()
	{
	    //关闭服务器端
	    sw_socket_close($this->server_sock,$this->server_event);
	    //关闭事件循环
        event_base_loopexit($this->base_event);
        $this->protocol->onShutdown();
	}
}

function sw_server_handle_recvfrom($server_socket,$events,$server)
{
    $data = stream_socket_recvfrom($server_socket,$server->buffer_size,$server->flags, $peer);
    if($data !== false && $data !='')
	{
		$server->protocol->onData($peer,$data);
	}
}