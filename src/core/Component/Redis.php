<?php
namespace SPF\Component;
use SPF;

/**
 * Class Redis
 * @package SPF\Component
 */
class Redis
{
    const READ_LINE_NUMBER = 0;
    const READ_LENGTH = 1;
    const READ_DATA = 2;

    /**
     * @var \Redis
     */
    protected $_redis;

    public $config;

    public static $prefix = "autoinc_key:";

    /**
     * 获取自增ID
     * @param $appKey
     * @param int $init_id
     * @return bool|int
     */
    function getIncreaseId($appKey, $init_id = 1)
    {
        if (empty($appKey))
        {
            return false;
        }
        $main_key = self::$prefix . $appKey;
        //已存在 就加1
        if ($this->_redis->exists($main_key))
        {
            $inc = $this->_redis->incr($main_key);
            if (empty($inc))
            {
                SPF\App::getInstance()->log->put("redis::incr() failed. Error: ".$this->_redis->getLastError());
                return false;
            }
            return $inc;
        }
        //上面的if条件返回false,可能是有错误，或者key不存在，这里要判断下
        else if($this->_redis->getLastError())
        {
            return false;
        }
        //这才是说明key不存在，需要初始化
        else
        {
            $init = $this->_redis->set($main_key, $init_id);
            if ($init == false)
            {
                SPF\App::getInstance()->log->put("redis::set() failed. Error: ".$this->_redis->getLastError());
                return false;
            }
            else
            {
                return $init_id;
            }
        }
    }

    function __construct($config)
    {
        $this->config = $config;
        $this->connect();
    }

    function connect()
    {
        try
        {
            $this->_redis = new \Redis();
            if (!empty($this->config['pconnect']))
            {
                // 判断是否使用socket
                if (isset($this->config['socket'])) {
                    $this->_redis->pconnect($this->config['socket']);
				} else {
                    $this->_redis->pconnect($this->config['host'], $this->config['port'], $this->config['timeout']);
				}
            }
            else
            {
                if (isset($this->config['socket'])) {
					$this->_redis->connect($this->config['socket']);
				} else {
					$this->_redis->connect($this->config['host'], $this->config['port'], $this->config['timeout']);
				}
            }
            
            if (!empty($this->config['password']))
            {
                $this->_redis->auth($this->config['password']);
            }
            if (!empty($this->config['database']))
            {
                $this->_redis->select($this->config['database']);
            }
        }
        catch (\RedisException $e)
        {
            SPF\App::getInstance()->log->error(__CLASS__ . " SPF Redis Exception, msg:".$e->getMessage()." code:
            ".$e->getCode()." line :".$e->getLine(). " file: ".$e->getFile());
            return false;
        }
    }

    function __call($method, $args = array())
    {
        $reConnect = false;
        while (1)
        {
            try
            {
                $result = call_user_func_array(array($this->_redis, $method), $args);
            }
            catch (\RedisException $e)
            {
                //已重连过，仍然报错
                if ($reConnect)
                {
                    throw $e;
                }

                SPF\App::getInstance()->log->error(__CLASS__ . " [" . posix_getpid() . "] SPF Redis[{$this->config['host']}:{$this->config['port']}]
                 Exception(Msg=" . $e->getMessage() . ", Code=" . $e->getCode() . "), Redis->{$method}, Params=" . var_export($args, 1));
                if ($this->_redis->isConnected())
                {
                    $this->_redis->close();
                }
                $this->connect();
                $reConnect = true;
                continue;
            }
            return $result;
        }
        //不可能到这里
        return false;
    }

    static function write($fp, $content)
    {
        $length = strlen($content);
        for ($written = 0; $written < $length; $written += $n)
        {
            if ($length - $written >= 8192)
            {
                $n = fwrite($fp, substr($content, 8192));
            }
            else
            {
                $n = fwrite($fp, substr($content, $written));
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
     * @param $file
     * @param $dstRedisServer
     * @param int $seek
     * @return bool
     */
    static function syncFromAof($file, $dstRedisServer, $seek = 0)
    {
        $fp = fopen($file, 'r');
        if (!$fp)
        {
            return false;
        }
        //偏移
        if ($seek > 0)
        {
            fseek($fp, $seek);
        }

        //目标Redis服务器
        $dstRedis = stream_socket_client($dstRedisServer, $errno, $errstr, 10);
        if (!$dstRedis)
        {
            return false;
        }

        $n_bytes = $seek;
        $n_lines = 0;
        $n_success = 0;
        $_send = '';
        $patten = "#^\\*(\d+)\r\n$#";

        readfile:
        while(!feof($fp))
        {
            $line = fgets($fp, 8192);
            if ($line === false)
            {
                echo "line empty\n";
                break;
            }
            $n_lines++;
            $r = preg_match($patten, $line);
            if ($r)
            {
                if ($_send)
                {
                    if (self::write($dstRedis, $_send) === false)
                    {
                        die("写入Redis失败. $_send");
                    }
                    $n_bytes += strlen($_send);
                    //清理数据
                    if (fread($dstRedis, 8192) == false)
                    {
                        echo "读取Redis失败. $_send\n";
                        for($i = 0; $i < 10; $i++)
                        {
                            $dstRedis = stream_socket_client($dstRedisServer, $errno, $errstr, 10);
                            if (!$dstRedis)
                            {
                                echo "连接到Redis($dstRedisServer)失败, 1秒后重试.\n";
                                sleep(1);
                            }
                        }
                        if (!$dstRedis)
                        {
                            echo "连接到Redis($dstRedisServer)失败\n";
                            return false;
                        }
                        $_send = $line;
                        continue;
                    }

                    $n_success ++;
                    if ($n_success % 10000 == 0)
                    {
                        $seek = ftell($fp);
                        echo "KEY: $n_success, LINE: $n_lines, BYTE: {$n_bytes}, SEEK: {$seek}. 完成\n";
                    }
                }
                $_send = $line;
            }
            else
            {
                $_send .= $line;
            }
        }

        wait:
        //等待100ms后继续读
        sleep(2);
        $seek = ftell($fp);
        echo "read eof, seek={$seek}\n";
        //关闭文件
        fclose($fp);
        $fp = fopen($file, 'r');
        if (!$fp)
        {
            exit("打开文件失败，seek=$seek\n");
        }
        if (fseek($fp, $seek) < 0)
        {
            exit("feek($seek)失败\n");
        }
        goto readfile;
    }
}
