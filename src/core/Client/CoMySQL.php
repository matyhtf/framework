<?php

namespace SPF\Client;

use SPF;
use SPF\Async\MySQL;
use SPF\Database\MySQLi;
use SPF\Database\MySQLiRecord;
use SPF\App;

/**
 * 并发MySQL客户端
 * concurrent mysql client
 * Class CoMySQL
 * @package SPF\Client
 */
class CoMySQL
{
    protected $config;
    protected $list;
    protected $results;
    protected $sqlIndex = 0;
    protected $pool = array();

    public function __construct($db_key = 'master')
    {
        $this->config = SPF\App::getInstance()->config['db'][$db_key];
        //不能使用长连接，避免进程内占用大量连接
        $this->config['persistent'] = false;
    }

    protected function getConnection()
    {
        //没有可用的连接
        if (count($this->pool) == 0) {
            $db = new MySQLi($this->config);
            $db->connect();
            return $db;
        } //从连接池中取一个
        else {
            return array_pop($this->pool);
        }
    }

    /**
     * @param $sql
     * @param null $callback
     * @return bool|CoMySQLResult
     */
    public function query($sql, $callback = null)
    {
        $db = $this->getConnection();
        $result = $db->queryAsync($sql);
        if (!$result) {
            return false;
        }
        $link = $db->getConnection();
        $retObj = new CoMySQLResult($link, $callback);
        $retObj->sql = $sql;
        $retObj->id = spl_object_hash($link);
        $this->list[$retObj->id] = $retObj;
        return $retObj;
    }

    public function wait($timeout = 1.0)
    {
        $_timeout_sec = intval($timeout);
        $_timeout_usec = intval(($timeout - $_timeout_sec) * 1000 * 1000);
        $taskSet = $this->list;

        $processed = 0;
        do {
            $links = $errors = $reject = array();
            /**
             * @var $retObj CoMySQLResult
             */
            foreach ($taskSet as $k => $retObj) {
                $links[] = $errors[] = $reject[] = $retObj->db;
            }
            //wait mysql server response
            if (!mysqli_poll($links, $errors, $reject, $_timeout_sec, $_timeout_usec)) {
                continue;
            }
            /**
             * @var $link mysqli
             */
            foreach ($links as $link) {
                $_retObj = $this->list[spl_object_hash($link)];
                $result = $link->reap_async_query();
                if ($result) {
                    if (is_object($result)) {
                        $_retObj->result = new MySQLiRecord($result);
                        if ($_retObj->callback) {
                            call_user_func($_retObj->callback, $_retObj->result);
                        }
                        $_retObj->code = 0;
                    } else {
                        $_retObj->code = CoMySQLResult::ERR_NO_OBJECT;
                    }
                } else {
                    trigger_error(sprintf("MySQLi Error: %s", $link->error));
                    $_retObj->code = $link->errno;
                }
                //从任务队列中移除
                unset($taskSet[$link->_co_id]);
                $processed++;
            }
        } while ($processed < count($this->list));
        //将连接重新放回池中
        foreach ($this->list as $_retObj) {
            $this->pool[] = $_retObj->db;
        }
        //初始化数据
        $this->list = array();
        $this->sqlIndex = 0;
        return $processed;
    }
}
