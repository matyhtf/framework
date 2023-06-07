<?php

namespace SPF\Coroutine\Component;

use SPF\Coroutine\BaseContext;
use SPF\Coroutine\MySQL as CoMySQL;
use SPF\IDatabase;
use SPF\IDbRecord;

class MySQL extends Base implements IDatabase
{
    protected $type = 'mysql';

    public function __construct($config)
    {
        parent::__construct($config);
        \SPF\App::getInstance()->beforeAction([$this, '_createObject'], \SPF\App::coroModuleDb);
        \SPF\App::getInstance()->afterAction([$this, '_freeObject'], \SPF\App::coroModuleDb);
    }

    public function create()
    {
        $db = new CoMySQL;
        if ($db->connect($this->config) === false) {
            return false;
        } else {
            return $db;
        }
    }

    public function query($sql)
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        $result = false;
        for ($i = 0; $i < 2; $i++) {
            $result = $db->query($sql);
            if ($result === false) {
                $db->close();
                BaseContext::delete($this->type);
                $db = $this->_createObject();
                continue;
            }
            break;
        }

        return new MySQLRecordSet($result);
    }

    public function quote($val)
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }
        if (empty($val)) {
            return $val;
        }
        return $db->escape($val);
    }

    public function lastInsertId()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        return $db->insert_id;
    }

    public function getAffectedRows()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return false;
        }

        return $db->affected_rows;
    }

    public function errno()
    {
        /**
         * @var $db CoMySQL
         */
        $db = $this->_getObject();
        if (!$db) {
            return -1;
        }

        return $db->errno;
    }

    public function close()
    {
    }

    public function connect()
    {
    }
}

class MySQLRecordSet implements IDbRecord
{
    public $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    public function fetch()
    {
        return isset($this->result[0]) ? $this->result[0] : null;
    }

    public function fetchall()
    {
        return $this->result;
    }

    public function __get($key)
    {
        return $this->result->$key;
    }
}
