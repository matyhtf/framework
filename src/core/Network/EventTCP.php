<?php
namespace SPF\Network;

class EventTCP extends \SPF\Server\Base
{
	/**
	 * Server Socket
	 * @var unknown_type
	 */
	public $base_event;
	public $server_event;
	public $server_sock;

    public $client_event;

	//最大连接数
	public $max_connect= 10000;

	//客户端socket列表
	public $client_sock = array();
	//客户端数量
	public $client_num = 0;

	function __construct($host, $port, $timeout=30)
	{
		parent::__construct($host, $port, $timeout);
	}

    function init()
	{
		$this->base_event = event_base_new();
		$this->server_event = event_new();
	}
	/**
	 * 运行服务器程序
	 * @return unknown_type
	 */
	function run($setting)
	{
		$this->init();
		//建立服务器端Socket
		$this->server_sock = $this->create("tcp://{$this->host}:{$this->port}");

		//设置事件监听，监听到服务器端socket可读，则有连接请求
		event_set($this->server_event,$this->server_sock, EV_READ | EV_PERSIST, array($this, "event_connect"));
		event_base_set($this->server_event,$this->base_event);
		event_add($this->server_event);
		$this->spawn($setting);
		$this->protocol->onStart($this);
		event_base_loop($this->base_event);
	}
	/**
	 * 向client发送数据
	 * @param $client_id
	 * @param $data
	 * @return unknown_type
	 */
	function send($client_id,$data)
	{
		$this->sendData($this->client_sock[$client_id],$data);
	}
	/**
	 * 向所有client发送数据
	 * @return unknown_type
	 */
	function sendAll($client_id,$data)
	{
		foreach($this->client_sock as $k=>$sock)
		{
			if($client_id and $k==$client_id) continue;
			$this->sendData($sock,$data);
		}
	}
	/**
	 * 关闭服务器程序
	 * @return unknown_type
	 */
	function shutdown()
	{
		//关闭所有客户端
		foreach($this->client_sock as $k=>$sock)
		{
            Stream::close($sock, $this->client_event[$k]);
		}
		//关闭服务器端
        Stream::close($this->server_sock, $this->server_event);
		//关闭事件循环
		event_base_loopexit($this->base_event);
		$this->protocol->onShutdown($this);
	}
	/**
	 * 关闭某个客户端
	 * @return unknown_type
	 */
	function close($client_id)
	{
		Stream::close($this->client_sock[$client_id],$this->client_event[$client_id]);
		unset($this->client_sock[$client_id],$this->client_event[$client_id]);
		$this->protocol->onClose($this, $client_id, 0);
		$this->client_num--;
	}

    /**
     * 处理客户端连接请求
     * @param $server_socket
     * @param $events
     * @param $server
     * @return unknown_type
     */
    function event_connect($server_socket, $events)
    {
        if($client_id = $this->accept())
        {
            $client_socket = $this->client_sock[$client_id];
            //新的事件监听，监听客户端发生的事件
            $client_event = event_new();
            event_set($client_event, $client_socket, EV_READ | EV_PERSIST, array($this, "event_receive"), $client_id);
            //设置基本时间系统
            event_base_set($client_event, $this->base_event);
            //加入事件监听组
            event_add($client_event);
            $this->client_event[$client_id] = $client_event;
            $this->protocol->onConnect($this, $client_id, 0);
        }
    }

    /**
     * 接收到数据后进行处理
     * @param $client_socket
     * @param $events
     * @param $arg
     * @return unknown_type
     */
    function event_receive($client_socket, $events, $client_id)
    {
        $data = Stream::read($client_socket, $this->buffer_size);
        if($data !== false)
        {
            $this->protocol->onReceive($this, $client_id, 0, $data);
        }
        else
        {
            $this->close($client_id);
        }
    }
}

