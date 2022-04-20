<?php
namespace SPF\Lock;
class File
{
    private $name;
    private $handle;
    private $mode;

    function __construct($filename, $mode = 'a+b')
    {
        global $php_errormsg;
        $this->name = $filename;
        $path = dirname($this->name);
        if ($path == '.' || !is_dir($path))
        {
            global $config_file_lock_path;
            $this->name = str_replace(array("/", "\\"), array("_", "_"), $this->name);
            if ($config_file_lock_path == null)
            {
                $this->name = dirname(__FILE__) . "/lock/" . $this->name;
            }
            else
            {
                $this->name = $config_file_lock_path . "/" . $this->name;
            }
        }
        $this->mode = $mode;
        $this->handle = fopen($this->name, $mode);
        if ($this->handle == false)
        {
            throw new \Exception($php_errormsg);
        }
    }

    public function close()
    {
        if ($this->handle !== null ) {
            @fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
    public function lock($lockType, $nonBlockingLock = false)
    {
        if ($nonBlockingLock) {
            return flock($this->handle, $lockType | LOCK_NB);
        } else {
            return flock($this->handle, $lockType);
        }
    }

    public function readLock()
    {
        return $this->lock(LOCK_SH);
    }

    public function writeLock($wait = 0.1)
    {
        $startTime = microtime(true);
        do
        {
            $canWrite = flock($this->handle, LOCK_EX);
            if(!$canWrite)
            {
                usleep(rand(10, 1000));
            }
        }
        while ((!$canWrite) && ((microtime(true) - $startTime) < $wait));
    }

    /**
     * if you want't to log the number under multi-thread system,
     * please open the lock, use a+ mod. then fopen the file will not
     * destroy the data.
     *
     * this function increment a delt value , and save to the file.
     *
     * @param int $delt
     * @return int
     */
    public function increment($delt = 1)
    {
        $n = $this->get();
        $n += $delt;
        $this->set($n);
        return $n;
    }

    public function get()
    {
        fseek($this->handle, 0);
        return (int)fgets($this->handle);
    }

    public function set($value)
    {
        ftruncate($this->handle, 0);
        return fwrite($this->handle, (string)$value);
    }

    public function unlock()
    {
        if ($this->handle !== null )
        {
            return flock($this->handle, LOCK_UN);
        }
        else
        {
            return true;
        }
    }
}