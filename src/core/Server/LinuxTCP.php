<?php
/**
 * Linux下的TCPServer，在这里使用了平台相关的特性，非Linux系统可能无法使用
 * @author Administrator
 *
 */
class LinuxTCP
{
	public $main_pid;
	public $cfg;
	public $os;
	public $socket_life = 300;

	//会话上下文
	protected $context;
	//缓存输出
	protected $buffer;
	//记录系统时间
	public $server_time;

	function __construct($cfg)
	{
		$this->cfg = $cfg;
		$this->os = new Linux;
		$this->server_time = time();
	}
	/**
	 * 根据配置初始化服务器
	 */
	function init_pcntl()
	{
        if (isset($this->cfg['pcntl']['daemon']))
        {
            $this->daemon();
        }
        $this->main_pid = posix_getpid();
        if (isset($this->cfg['pcntl']['user']))
        {
            $user = posix_getpwnam($this->cfg['pcntl']['user']);
            $this->setuid($user['uid'], $user['gid']);
        }
        if (isset($this->cfg['pcntl']['pid_file']))
        {
            file_put_contents($this->cfg['pcntl']['pid_file'], $this->main_pid);
        }
        if (isset($this->cfg['pcntl']['chroot']))
        {
            chroot($this->cfg['pcntl']['chroot']);
        }
	}
	function init_signal()
	{
		if(isset($this->cfg['signal']['alarm_sec']))
		{
			$this->init_alarm();
		}
	}
	function signal_handler()
	{

	}

    function alarm_handler()
    {
        $this->run_check();
        $this->init_alarm();
    }

    function init_alarm()
    {
        $this->os->alarm($this->cfg['signal']['alarm_sec']);
        $this->os->signal(SIGALRM, array($this, 'alarm_handler'));
        $this->server_time = time();
    }

    function keeplive($client_id)
    {
        $this->context[$client_id]['time'] = $this->server_time;
    }

    /**
     * 清理socket
     */
    function clean_socket()
    {
        if (empty($this->server->client_sock))
        {
            return true;
        }
        foreach ($this->server->client_sock as $k => $fd)
        {
            //echo $this->context[$k]['time'],"\t",$this->socket_life,"\t",$this->server_time,"\n";
            if ($this->context[$k]['time'] < $this->server_time - $this->socket_life)
            {
                $this->server->close($k);
            }
        }
    }

    /**
     * 守护进程化
     */
    function daemon()
    {
        $pid = pcntl_fork();
        if ($pid < 0)
        {
            exit("Exit: Fork fail\n");
        }
        elseif ($pid == 0)
        {
            exit(0);
        }
    }

    /**
     * 设置运行的uid,gid
     * @param $uid
     * @param $gid
     */
    function setuid($uid, $gid)
    {
        posix_setuid($uid);
        posix_setgid($gid);
    }
}