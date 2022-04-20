<?php
namespace SPF\Database;

use SPF;

/**
 * 数据库代理服务，实现读写分离
 * @package SPF\Database
 */
class Proxy
{
    /**
     * 强制发往主库
     * @var bool
     */
    public $forceMaster = false;
    protected $config;

    /**
     * @var SPF\Database
     */
    protected $slaveDB;

    /**
     * @var SPF\Database
     */
    protected $masterDB;

    const DB_MASTER = 1;
    const DB_SLAVE = 2;

    function __construct($config)
    {
        if (empty($config['slaves']))
        {
            throw new LocalProxyException("require slaves options.");
        }
        $this->config = $config;
    }

    protected function getDB($type = self::DB_SLAVE)
    {
        if ($this->forceMaster)
        {
            goto master;
        }

        //只读的语句
        if ($type == self::DB_SLAVE)
        {
            if (empty($this->slaveDB))
            {
                //连接到从库
                $config = $this->config;
                //从从库中随机选取一个
                $server = SPF\Tool::getServer($config['slaves']);
                unset($config['slaves'], $config['use_proxy']);
                $config['host'] = $server['host'];
                $config['port'] = $server['port'];
                $this->slaveDB = $this->connect($config);
            }
            return $this->slaveDB;
        }
        else
        {
            master:
            if (empty($this->masterDB))
            {
                //连接到主库
                $config = $this->config;
                unset($config['slaves'], $config['use_proxy']);
                $this->masterDB = $this->connect($config);
            }
            return $this->masterDB;
        }
    }

    function query($sql)
    {
        $command = substr($sql, 0, 6);
        //只读的语句
        if (strcasecmp($command, 'select') === 0)
        {
           if($this->forceMaster){
             $sql = "/*master*/".$sql;
            }
            $db = $this->getDB(self::DB_SLAVE);
        }
        else
        {
            $this->forceMaster = true;
            $db = $this->getDB(self::DB_MASTER);
        }
        return $db->query($sql);
    }

    protected function connect($config)
    {
        $db = new SPF\Database($config);
        $db->connect();
        return $db;
    }

    function __call($method, $args)
    {
        $db = $this->getDB(false);
        return call_user_func_array(array($db, $method), $args);
    }
}

class LocalProxyException extends \Exception
{

}
