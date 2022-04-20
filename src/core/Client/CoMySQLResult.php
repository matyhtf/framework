<?php

namespace SPF\Client;

use mysqli;
use SPF\Database\MySQLiRecord;

class CoMySQLResult
{
    public $id;
    /**
     * @var mysqli
     */
    public $db;
    public $callback = null;
    /**
     * @var MySQLiRecord
     */
    public $result;
    public $sql;
    public $code = self::ERR_NO_READY;

    const ERR_NO_READY = 6001;
    const ERR_TIMEOUT = 6002;
    const ERR_NO_OBJECT = 6003;

    function __construct(mysqli $db, callable $callback = null)
    {
        $this->db = $db;
        $this->callback = $callback;
    }
}