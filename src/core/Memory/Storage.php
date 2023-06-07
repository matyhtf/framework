<?php
namespace SPF\Memory;

use SPF\Exception\Syscall;
use SPF\Tool;

class Storage
{
    public static $shmDir = '/dev/shm';
    public static $separator = ':';

    protected $baseDir;
    protected $mode;

    public function __construct($subdir = 'swoole', $mode = 0777)
    {
        $this->baseDir = self::$shmDir . '/' . $subdir;
        $this->mode = $mode;
        if (!is_dir($this->baseDir)) {
            Syscall::mkdir($this->baseDir, $this->mode, true);
        }
    }

    protected function getFile($key, $createDir = false)
    {
        $file = $this->baseDir . '/' . str_replace(self::$separator, '/', trim($key, self::$separator));
        $dir = dirname($file);
        if ($createDir and !is_dir($dir)) {
            Syscall::mkdir($dir, $this->mode, true);
        }
        return $file;
    }

    public function get($key)
    {
        $file = $this->getFile($key);
        if (!is_file($file)) {
            return false;
        }
        $res = Tool::readFile($file);
        if ($res) {
            return unserialize($res);
        } else {
            return false;
        }
    }

    public function set($key, $value)
    {
        $file = $this->getFile($key, true);
        if (file_put_contents($file, serialize($value), LOCK_EX) === false) {
            return false;
        } else {
            return true;
        }
    }

    public function exists($key)
    {
        return is_file($this->getFile($key));
    }

    public function scan($prefix)
    {
        $dir = $this->baseDir . '/' . str_replace(self::$separator, '/', trim($prefix, self::$separator));
        if (!is_dir($dir)) {
            return false;
        }
        return Tool::scandir($dir);
    }

    public function del($key)
    {
        $file = $this->getFile($key);
        return unlink($file);
    }
}
