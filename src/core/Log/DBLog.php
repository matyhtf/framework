<?php
namespace SPF\Log;
use SPF;

/**
 * 数据库日志记录类
 * @author Tianfeng.Han
 */
class DBLog extends \SPF\Log implements \SPF\IFace\Log
{
    /**
     * @var \SPF\Database;
     */
    protected $db;
    protected $table;

    function __construct($config)
    {
        if (empty($config['table']))
        {
            throw new \Exception(__CLASS__ . ": require \$config['table']");
        }
        $this->table = $config['table'];
        if (isset($config['db']))
        {
            $this->db = SPF\App::getInstance()->db($config['db']);
        }
        else
        {
            $this->db = SPF\App::getInstance()->db('master');
        }
        parent::__construct($config);
    }

    function put($msg, $level = self::INFO)
    {
        $put['logtype'] = self::convert($level);
        $msg = $this->format($msg, $level);
        if ($msg)
        {
            $put['msg'] = $msg;
            SPF\App::getInstance()->db->insert($put, $this->table);
        }
    }

    function create()
    {
        return $this->db->query("CREATE TABLE `{$this->table}` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `addtime` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,
            `logtype` TINYINT NOT NULL ,
            `msg` VARCHAR(255) NOT NULL
            )");
    }
}
