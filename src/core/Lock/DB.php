<?php
namespace SPF\Lock;

class DB
{
    public $lockname;
    public $timeout;
    public $locked;

    public function __construct($name, $timeout = 0)
    {
        $this->lockname = $name;
        $this->timeout = $timeout;
        $this->locked = -1;
    }

    public function lock()
    {
        $rs = qdb("SELECT GET_LOCK('".$this->lockname."', ".$this->timeout.")");
        $this->locked = result($rs, 0);
        mysqli_free_result($rs);
    }

    public function release()
    {
        $rs = qdb("SELECT RELEASE_LOCK('".$this->lockname."')");
        $this->locked = !result($rs, 0);
        mysqli_free_result($rs);
    }

    public function isFree()
    {
        $rs = qdb("SELECT IS_FREE_LOCK('".$this->lockname."')");
        $lock = (bool)result($rs, 0);
        mysqli_free_result($rs);

        return $lock;
    }
}
