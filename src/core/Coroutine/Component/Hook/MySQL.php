<?php

namespace SPF\Coroutine\Component\Hook;

use SPF\Coroutine\Component\Base;
use SPF\Coroutine\BaseContext;
use SPF\Database\MySQLi as CoMysql;

class MySQL extends Base
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
        $db = new CoMySQL($this->config);
        if ($db->connect() === false) {
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

        return $result;
    }

    /**
     * 调用$driver的自带方法
     * @param $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args = array())
    {
        $obj = $this->_getObject();
        if (!$obj) {
            return false;
        }
        return $obj->{$method}(...$args);
    }
}
