<?php

namespace SPF\Coroutine;

use SPF;

class Memcache
{
    const CONNECTION_REPL_NUM = 1024;

    const OK = 1;
    const STORED = 2;
    const NOT_STORED = 3;
    const DELETED = 4;
    const NOT_FOUND = 5;
    const ERROR = 6;
    const CLIENT_ERROR = 7;
    const SERVER_ERROR = 8;
    const VALUE = 9;
    const STATS = 10;
    const END = 11;

    const VAL_IS_STRING  = 0;
    const VAL_IS_LONG    = 1;
    const VAL_IS_DOUBLE  = 2;
    const VAL_IS_BOOL    = 3;
    const VAL_IS_SERIALIZED = 4;
    //TODO inplement with following type
    const VAL_IS_IGBINARY   = 5;
    const VAL_IS_JSON       = 6;
    const VAL_IS_MSGPACK    = 7;

    public $errCode;

    protected $servers = array();
    /**
     * 开启一致性 hash
     * @var bool
     */
    protected $consistentHash = false;
    protected $pool;
    protected $timeout = 0.5;

    public function addServer($ip, $port, $weight = 1)
    {
        $this->servers[] = ['host' => $ip, 'port' => $port, 'weight' => $weight];
    }

    public function addServers(array $servers)
    {
        foreach ($servers as $s) {
            if (empty($s['host'])) {
                continue;
            }
            if (empty($s['port'])) {
                continue;
            }
            if (empty($s['weight'])) {
                $s['weight'] = 1;
            }
            $this->addServer($s['host'], $s['port'], $s['weight']);
        }
    }


    public function add($key, $value, $expire = 0)
    {
        list($value, $flag) = $this->payloadValue($value);
        $result = $this->request($key, "add $key $flag $expire " . strlen($value) . "\r\n$value\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] == self::STORED) {
            return true;
        } else {
            return false;
        }
    }

    public function set($key, $value, $expire = 0)
    {
        list($value, $flag) = $this->payloadValue($value);
        $result = $this->request($key, "set $key $flag $expire " . strlen($value) . "\r\n$value\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] == self::STORED) {
            return true;
        } else {
            return false;
        }
    }

    public function replace($key, $value, $expire = 0)
    {
        list($value, $flag) = $this->payloadValue($value);
        $result = $this->request($key, "replace $key $flag $expire " . strlen($value) . "\r\n$value\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] == self::STORED) {
            return true;
        } else {
            return false;
        }
    }

    public function getMulti(array $keys)
    {
        $count = count($keys) * 2 + 1;
        $result = $this->request($keys[0], "get " . implode(' ', $keys) . "\r\n", $count);
        if ($result === false) {
            return false;
        }

        if ($result['type'] != self::VALUE) {
            $this->errCode = $result['type'];

            return false;
        }
        $_retval = [];
        foreach ($keys as $k) {
            if (isset($result['data'][$k]['value'])) {
                $_retval[$k] = $this->parseResultToValue($result['data'][$k]);
            } else {
                $_retval[$k] = false;
            }
        }

        return $_retval;
    }

    public function get($key, &$flag = array())
    {
        $result = $this->request($key, "get $key\r\n", 3);

        if ($result === false) {
            return false;
        }

        if ($result['type'] != self::VALUE) {
            $this->errCode = $result['type'];

            return false;
        }
        return $this->parseResultToValue($result['data'][$key]);
    }

    public function increment($key, $by = 1)
    {
        $result = $this->request($key, "incr $key $by\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] != self::OK) {
            $this->errCode = $result['type'];
            return false;
        } else {
            return intval($result['data']);
        }
    }

    public function decrement($key, $by = 1)
    {
        $result = $this->request($key, "decr $key $by\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] != self::OK) {
            $this->errCode = $result['type'];

            return false;
        } else {
            return intval($result['data']);
        }
    }

    public function delete($key, $time = 0)
    {
        $result = $this->request($key, "delete $key $time\r\n");
        if ($result === false) {
            return false;
        }
        if ($result['type'] == self::DELETED) {
            return true;
        } else {
            return false;
        }
    }

    public function getStats($serverKey = '')
    {
        if (empty($serverKey)) {
            $serverKey = $this->servers[0]['host'].':'.$this->servers[0]['port'];
        }
        $connection = $this->_getConnection($serverKey);
        if ($connection->send("stats\r\n") == false) {
            return false;
        }
        $data = $connection->recv();
        if ($data == false) {
            $connection->close();
            return false;
        }
        $result = $this->decode($data);
        if ($result['type'] != self::STATS) {
            $this->errCode = $result['type'];

            return false;
        }
        return [$serverKey => $result['data']];
    }

    //TODO other methods implement in memcached

    /**
     * @param $key
     * @param $cmd
     * @param int $times
     * @return array|bool|false
     */
    protected function request($key, $cmd, $times = 1)
    {
        if ($this->consistentHash) {
            $server = $this->consistentHash();
        } elseif (count($this->servers) == 1) {
            $server = $this->servers[0];
        } else {
            $hash = swoole_hashcode($key, 1);
            $server = $this->servers[$hash % count($this->servers)];
        }

        $serverKey = $server['host'] . ':' . $server['port'];
        $connection = $this->_getConnection($serverKey);
        if ($connection->send($cmd) == false) {
            $connection->close();

            return false;
        }

        $data = "";
        if ($times > 1) {
            for ($i = 0; $i < $times; $i++) {
                $tmp = $connection->recv();
                if ($tmp == false) {
                    return false;
                }
                $data .= $tmp;
            }
        } else {
            $data = $connection->recv();
        }
        if ($data == false) {
            $connection->close();

            return false;
        }

        //释放连接
        $this->_freeConnection($serverKey, $connection);

        return $this->decode($data);
    }


    /**
     * @param $buffer
     * @return array
     */
    protected static function decode($buffer)
    {
        if (substr($buffer, 0, 4) == 'STAT') {
            $stats = array();

            $lines = explode("\r\n", $buffer);
            foreach ($lines as $line) {
                if (substr($line, 0, 4) == 'STAT') {
                    list(, $key, $value) = explode(' ', $line);
                    $stats[$key] = $value;
                }
            }

            return ['data' => $stats, 'type' => self::STATS];
        } elseif (substr($buffer, 0, 5) == 'VALUE') {
            $lines = explode("\r\n", $buffer);
            $lines = array_slice($lines, 0, count($lines) - 2);

            $data = array();
            foreach ($lines as $index => $line) {
                /**
                 * @var $key
                 * @var $flag
                 * @var $bytes
                 */
                if (($index % 2) == 0) {
                    list(, $key, $flag, $bytes) = explode(' ', $line);
                } else {
                    $data[$key] = array('value' => $line, 'flag' => $flag, 'bytes' => $bytes);
                }
            }

            return ['data' => $data, 'type' => self::VALUE];
        } elseif (substr($buffer, 0, 6) == 'STORED') {
            return ['type' => self::STORED];
        } elseif (substr($buffer, 0, 10) == 'NOT_STORED') {
            return ['type' => self::NOT_STORED];
        } elseif (substr($buffer, 0, 7) == 'DELETED') {
            return ['type' => self::DELETED];
        } elseif (substr($buffer, 0, 9) == 'NOT_FOUND') {
            return ['type' => self::NOT_FOUND];
        } elseif (substr($buffer, 0, 3) == 'END') {
            return ['type' => self::END];
        } elseif (substr($buffer, 0, 7) == 'VERSION') {
            list(, $version) = explode(' ', trim($buffer, "\r\n"));

            return ['type' => self::END, 'data' => $version];
        } else {
            list($value, ) = explode("\r\n", $buffer);

            return ['type' => self::OK, 'data' => $value];
        }
    }

    protected static function hash($key)
    {
        $value = 0;
        $key_length = strlen($key);
        $i = 0;

        while ($key_length--) {
            $val = ord($key[$i++]);
            $value += $val;
            $value += ($value << 10);
            $value ^= ($value >> 6);
        }
        $value += ($value << 3);
        $value ^= ($value >> 11);
        $value += ($value << 15);

        return $value;
    }

    protected function setTimeout($timeout)
    {
        if ($timeout) {
            $this->timeout = $timeout;
        }
    }

    protected function _getConnection($serverKey)
    {
        list($host, $port) = explode(':', $serverKey);
        //创建连接池
        if (!isset($this->pool[$serverKey])) {
            $this->pool[$serverKey] = new \SplQueue();
        }
        //有空闲连接
        if (count($this->pool[$serverKey]) > 0) {
            return $this->pool[$serverKey]->pop();
        } else {
            $conn = new SPF\Coroutine\Client(SWOOLE_SOCK_TCP);
            $conn->set(array('open_eof_check' => true, 'package_eof' => "\r\n"));
            if ($conn->connect($host, $port, $this->timeout) == false) {
                return false;
            } else {
                return $conn;
            }
        }
    }

    protected function _freeConnection($serverKey, $conn)
    {
        //创建连接池
        if (!isset($this->pool[$serverKey])) {
            $this->pool[$serverKey] = new \SplQueue();
        }
        $this->pool[$serverKey]->push($conn);
    }

    protected function payloadValue($value)
    {
        if (is_string($value)) {
            $val = strval($value);
            $flag = self::VAL_IS_STRING;
        } elseif (is_long($value)) {
            $val = strval($value);
            $flag = self::VAL_IS_LONG;
        } elseif (is_double($value)) {
            $val = strval($value);
            $flag = self::VAL_IS_DOUBLE;
        } elseif (is_bool($value)) {
            $val = $value ? "1" : "0";
            $flag = self::VAL_IS_BOOL;
        } else {
            $val = serialize($value);
            $flag = self::VAL_IS_SERIALIZED;
        }

        return [$val, $flag];
    }

    protected function parseResultToValue($result)
    {
        if (!isset($result['flag'])) {
            return false;
        }

        $flag = intval($result['flag']);
        switch ($flag) {
            case self::VAL_IS_STRING:
                $value = $result['value'];
                break;

            case self::VAL_IS_LONG:
                $value = intval($result['value']);
                break;

            case self::VAL_IS_DOUBLE:
                {
                    if ($result['value'] === "Infinity") {
                        $value = INF;
                    } elseif ($result['value'] === "-Infinity") {
                        $value = -INF;
                    } elseif ($result['value'] === "NaN") {
                        $value = NAN;
                    } else {
                        $value = doubleval($result['value']);
                    }
                }
                break;

            case self::VAL_IS_BOOL:
                $value = $result['value'] ? true : false;
                break;
            //TODO
            case self::VAL_IS_SERIALIZED:
            case self::VAL_IS_IGBINARY:
            case self::VAL_IS_JSON:
            case self::VAL_IS_MSGPACK:
                $value = unserialize($result['value']);
                break;
            default:
                $value = false;
        }

        return $value;
    }

    /**
     * TODO 一致性HASH，待实现
     */
    public function consistentHash()
    {
        return false;
    }
}
