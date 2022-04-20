<?php
namespace SPF\Client;

/**
 * TCP客户端
 * @author hantianfeng
 */
class Stream
{
    /**
     * 是否已连接
     * @var bool
     */
    public $connected = false;

    public $errCode = 0;
    public $errMsg = '';

    protected $fp;
    protected $tcp;

    function __construct($tcp = true)
    {
        $this->tcp = $tcp;
    }

    /**
     * @param $host
     * @param $port
     * @param float $timeout
     * @return bool
     */
    function connect($host, $port, $timeout = 0.1)
    {
        $uri = ($this->tcp ? 'tcp' : 'udp') . "://{$host}:{$port}";
        $this->fp = stream_socket_client($uri, $this->errCode, $this->errMsg, $timeout);
        if (!$this->fp)
        {
            return false;
        }
        $this->connected = true;

        return true;
    }

    /**
     * @param float $timeout
     * @return bool
     */
    function setTimeout($timeout)
    {
        $t_sec = (int)$timeout;
        $t_usec = (int)(($timeout - $t_sec) * 1000 * 1000);

        return stream_set_timeout($this->fp, $t_sec, $t_usec);
    }

    /**
     * 接收数据
     * @param $length
     * @return string
     */
    function recv($length)
    {
        $content = '';
        $readn = $length;

        while ($readn > 0)
        {
            if ($readn > 8192)
            {
                $tmp = fread($this->fp, 8192);
            }
            else
            {
                $tmp = fread($this->fp, $readn);
            }
            if (empty($tmp))
            {
                break;
            }
            $content .= $tmp;
            $readn -= strlen($tmp);
        }
        return $content;
    }

    /**
     * 发送数据
     * @param $content
     * @return int
     */
    function send($content)
    {
        $length = strlen($content);
        for ($written = 0; $written < $length; $written += $n)
        {
            if ($length - $written >= 8192)
            {
                $n = fwrite($this->fp, substr($content, 8192));
            }
            else
            {
                $n = fwrite($this->fp, substr($content, $written));
            }
            //写文件失败了
            if (empty($n))
            {
                break;
            }
        }

        return $written;
    }

    /**
     * @return mixed
     */
    function getSocket()
    {
        return $this->fp;
    }

    /**
     * 关闭socket连接
     * @return bool
     */
    function close()
    {
        if ($this->fp)
        {
            return fclose($this->fp);
        }
        return false;
    }
}