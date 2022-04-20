<?php

namespace SPF\Queue;

use SPF;

/**
 * 文件存储的队列
 * @package SPF\Queue
 */
class File implements SPF\IFace\Queue
{
    protected $queue;
    protected $file;
    protected $name;
    protected $dir;

    function __construct($config)
    {
        if (!empty($config['name'])) {
            $this->name = $config['name'];
        }
        if (!empty($config['dir'])) {
            $this->dir = $config['dir'];
        } else {
            $this->dir = SPF\App::getInstance()->getPath() . '/queue';
        }
        $this->file = $this->dir . '/' . $config['name'] . '.fq';
        $this->queue = new \SplQueue();
        $this->load();
    }

    function load()
    {
        if (!is_file($this->file)) {
            return false;
        }
        $fp = fopen($this->file, 'r');
        while (!feof($fp)) {
            $header = fread($fp, 4);
            if ($header == false) {
                break;
            }
            $info = unpack('Nlen', $header);
            $item = fread($fp, $info['len']);
            $this->queue->push($item);
        }
        fclose($fp);
        return true;
    }

    function save()
    {
        $fp = fopen($this->file, 'w+');
        ftruncate($fp, 0);
        foreach ($this->queue as $item) {
            fwrite($fp, pack('N', strlen($item)) . $item);
        }
        fclose($fp);
    }

    function push($data)
    {
        $this->queue->push(serialize($data));
    }

    function pop()
    {
        if (count($this->queue) == 0) {
            return null;
        }
        return $this->queue->shift();
    }

    function __destruct()
    {
        $this->save();
    }
}
