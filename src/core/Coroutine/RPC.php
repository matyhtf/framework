<?php
namespace SPF\Coroutine;

use SPF;
use SPF\Protocol\RPCServer;

class RPC extends SPF\Client\RPC
{
    public static $pool = array();

    public function __construct($id = null)
    {
        parent::__construct($id);
        //基于Swoole扩展
        if (!$this->haveSwoole or class_exists('SPF\Coroutine\Client', false) === false) {
            throw new \RuntimeException("require swoole-2.x extension.");
        }
    }

    /**
     * @param $key
     * @return \SplQueue
     */
    protected static function getPool($key)
    {
        if (!isset(self::$pool[$key])) {
            self::$pool[$key] = new \SplQueue();
        }

        return self::$pool[$key];
    }

    protected function select($read, $write, $error, $timeout)
    {
        return count($read);
    }

    protected function recvPacket($connection, $timeout=0.5)
    {
        if ($this->haveSwoole) {
            return $connection->recv($timeout);
        }

        /**
         * Stream or Socket
         */
        $_header_data = $connection->recv(RPCServer::HEADER_SIZE, true);
        if (empty($_header_data)) {
            return "";
        }
        //这里仅使用了length和type，uid,serid未使用
        $header = unpack(RPCServer::HEADER_STRUCT, $_header_data);
        //错误的包头，返回空字符串，结束连接
        if ($header === false or $header['length'] <= 0 or $header['length'] > $this->packet_maxlen) {
            return "";
        }

        $_body_data = $connection->recv($header['length'], true);
        if (empty($_body_data)) {
            return "";
        }
        return $_header_data . $_body_data;
    }

    protected function getConnection($host, $port)
    {
        $pool = self::getPool($host . ':' . $port);
        if (count($pool) > 0) {
            $socket = $pool->pop();
        } else {
            $socket = new Client(SWOOLE_SOCK_TCP);
            $socket->set(array(
                'open_length_check' => true,
                'package_max_length' => $this->packet_maxlen,
                'package_length_type' => 'N',
                'package_body_offset' => RPCServer::HEADER_SIZE,
                'package_length_offset' => 0,
            ));
            /**
             * 尝试重连一次
             */
            for ($i = 0; $i < 2; $i++) {
                $ret = $socket->connect($host, $port, $this->timeout);
                if ($ret === false and ($socket->errCode == 114 or $socket->errCode == 115)) {
                    //强制关闭，重连
                    $socket->close();
                    continue;
                } else {
                    break;
                }
            }
            $socket->host = $host;
            $socket->port = $port;
        }
        return $socket;
    }

    /**
     * @param $socket Client
     */
    protected function freeConnection($socket)
    {
        $pool = self::getPool($socket->host . ':' . $socket->port);
        $pool->push($socket);
    }
}
