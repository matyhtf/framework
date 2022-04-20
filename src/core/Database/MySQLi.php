<?php

namespace SPF\Database;

use SPF;

/**
 * MySQL数据库封装类
 * @author  Tianfeng.Han
 * @version 2.0.1
 */
class MySQLi implements SPF\IDatabase
{
    const DEFAULT_PORT = 3306;

    public $debug = false;
    public $conn = null;
    public $config;

    /**
     * @var $mysqli \mysqli
     */
    protected $mysqli;

    function __construct($db_config)
    {
        if (empty($db_config['port'])) {
            $db_config['port'] = self::DEFAULT_PORT;
        }
        $this->config = $db_config;
    }

    function lastInsertId()
    {
        return $this->mysqli->insert_id;
    }

    function close()
    {
        return $this->mysqli->close();
    }

    /**
     * 参数为了兼容parent类，代码不会使用传入的参数作为配置
     * @param null $_host
     * @param null $user
     * @param null $password
     * @param null $database
     * @param null $port
     * @param null $socket
     * @return bool
     */
    function connect($_host = null, $user = null, $password = null, $database = null, $port = null, $socket = null)
    {
        $db_config = &$this->config;
        $host = $db_config['host'];
        if (!empty($db_config['persistent'])) {
            $host = 'p:' . $host;
        }
        if (isset($db_config['passwd'])) {
            $db_config['password'] = $db_config['passwd'];
        }
        if (isset($db_config['dbname'])) {
            $db_config['name'] = $db_config['dbname'];
        } elseif (isset($db_config['database'])) {
            $db_config['name'] = $db_config['database'];
        }
        if (!isset($db_config['socket'])) {
            $db_config['socket'] = null;
		}
        $this->mysqli = mysqli_connect(
            $host,
            $db_config['user'],
            $db_config['password'],
            $db_config['name'],
            $db_config['port'],
            $db_config['socket']
        );
        if (!$this->mysqli) {
            trigger_error("mysqli connect to server[$host:{$db_config['port']}] failed: " . $this->mysqli->connect_error, E_USER_WARNING);
            return false;
        }
        if (!empty($db_config['charset'])) {
            $this->mysqli->set_charset($db_config['charset']);
        }
        return true;
    }

    /**
     * 过滤特殊字符
     * @param $value
     * @return string
     */
    function quote($value)
    {
        return $this->tryReconnect(array($this, 'escape_string'), array($value));
    }

    /**
     * SQL错误信息
     * @param $sql
     * @return string
     */
    protected function errorMessage($sql)
    {
        $msg = $this->mysqli->error . "<hr />$sql<hr />\n";
        $msg .= "Server: {$this->config['host']}:{$this->config['port']}. <br/>\n";
        if ($this->mysqli->connect_errno) {
            $msg .= "ConnectError[{$this->mysqli->connect_errno}]: {$this->mysqli->connect_error}<br/>\n";
        }
        $msg .= "Message: {$this->mysqli->error} <br/>\n";
        $msg .= "Errno: {$this->mysqli->errno}\n";
        return $msg;
    }

    /**
     * @param $call
     * @param $params
     * @return bool|mixed
     */
    protected function tryReconnect($call, $params)
    {
        $result = false;
        for ($i = 0; $i < 2; $i++) {
            /**
             * check mysql instance for connect failed and reset object
             */
            if (empty($call[0])) {
                if ($this->connect()) {
                    $call[0] = $this->mysqli;
                } else {
                    continue;
                }
            }
            $result = call_user_func_array($call, $params);
            if ($result === false) {
                if ($this->mysqli->errno == 2013 or $this->mysqli->errno == 2006 or ($this->mysqli->errno == 0 and !$this->mysqli->ping())) {
                    $r = $this->checkConnection();
                    $call[0] = $this->mysqli;
                    if ($r === true) {
                        continue;
                    }
                } else {
                    SPF\Error::info(__CLASS__ . " SQL Error", $this->errorMessage($params[0]));
                    return false;
                }
            }
            break;
        }
        return $result;
    }

    /**
     * 执行一个SQL语句
     * @param string $sql 执行的SQL语句
     * @param int $resultmode
     * @return MySQLiRecord | false
     */
    function query($sql, $resultmode = null)
    {
        $result = $this->tryReconnect(array($this->mysqli, 'query'), array($sql, $resultmode));
        if (!$result) {
            trigger_error(__CLASS__ . " SQL Error:" . $this->errorMessage($sql), E_USER_WARNING);

            return false;
        }
        if (is_bool($result)) {
            return $result;
        }
        return new MySQLiRecord($result);
    }

    /**
     * 执行多个SQL语句
     * @param string $sql 执行的SQL语句
     * @return MySQLiRecord | false
     */
    function multi_query($sql)
    {
        $result = $this->tryReconnect(array($this->mysqli, 'multi_query'), array($sql));
        if (!$result) {
            SPF\Error::info(__CLASS__ . " SQL Error", $this->errorMessage($sql));
            return false;
        }

        $result = call_user_func_array(array('parent', 'use_result'), array());
        $output = array();
        while ($row = $result->fetch_assoc()) {
            $output[] = $row;
        }
        $result->free();

        while (call_user_func_array(array('parent', 'more_results'), array()) && call_user_func_array(array(
                'parent',
                'next_result'
            ), array())) {
            $extraResult = call_user_func_array(array('parent', 'use_result'), array());
            if ($extraResult instanceof \mysqli_result) {
                $extraResult->free();
            }
        }

        return $output;
    }

    /**
     * 异步SQL
     * @param $sql
     * @return bool|\mysqli_result
     */
    function queryAsync($sql)
    {
        $result = $this->tryReconnect(array($this->mysqli, 'query'), array($sql, MYSQLI_ASYNC));
        if (!$result) {
            SPF\Error::info(__CLASS__ . " SQL Error", $this->errorMessage($sql));
            return false;
        }
        return $result;
    }

    /**
     * 检查数据库连接,是否有效，无效则重新建立
     */
    protected function checkConnection()
    {
        if (!@$this->mysqli->ping()) {
            $this->close();
            return $this->connect();
        }
        return true;
    }

    /**
     * @return \mysqli
     */
    function getConnection()
    {
        return $this->mysqli;
    }

    /**
     * 获取错误码
     * @return int
     */
    function errno()
    {
        return $this->mysqli->errno;
    }

    /**
     * 获取受影响的行数
     * @return int
     */
    function getAffectedRows()
    {
        return $this->mysqli->affected_rows;
    }

    /**
     * 返回上一个Insert语句的自增主键ID
     * @return int
     */
    function Insert_ID()
    {
        return $this->mysqli->insert_id;
    }

    function __call($func, $params)
    {
        return call_user_func_array(array($this->mysqli, $func), $params);
    }
}

class MySQLiRecord implements SPF\IDbRecord
{
    /**
     * @var \mysqli_result
     */
    public $result;

    function __construct($result)
    {
        $this->result = $result;
    }

    function fetch()
    {
        return $this->result->fetch_assoc();
    }

    function fetchall()
    {
        $data = array();
        while ($record = $this->result->fetch_assoc()) {
            $data[] = $record;
        }
        return $data;
    }

    function free()
    {
        $this->result->free_result();
    }

    function __get($key)
    {
        return $this->result->$key;
    }

    function __call($func, $params)
    {
        return call_user_func_array(array($this->result, $func), $params);
    }
}
