<?php
namespace SPF\Log;

/**
 * 文件日志类
 * @author Tianfeng.Han
 *
 */
class FileLog extends \SPF\Log implements \SPF\IFace\Log
{
    protected $log_file;
    protected $log_dir;
    protected $fp;
    //是否按日期存储日志
    protected $archive;
    //是否切割文件
    protected $cut_file = false;
    //待写入文件的日志队列（缓冲区）
    protected $queue = array();
    //是否记录更详细的信息（目前记多了文件名、行号）
    protected $verbose = false;
    protected $enable_cache = true;
    protected $date;

    public function __construct($config)
    {
        if (is_string($config)) {
            $file = $config;
            $config = array('file' => $file);
        }
        $this->cut_file = isset($config["cut_file"]) && $config["cut_file"] == true;
        $this->archive = isset($config['date']) && $config['date'] == true;
        $this->verbose = isset($config['verbose']) && $config['verbose'] == true;
        $this->enable_cache = isset($config['enable_cache']) ? (bool) $config['enable_cache'] : true;
        if (isset($config['level'])) {
            $this->log_line = (int) $config['level'];
        }

        //按日期存储日志
        if ($this->archive) {
            if (isset($config['dir'])) {
                $this->date = date('Ymd');
                $this->log_dir = rtrim($config['dir'], '/');
                $this->log_file = $this->log_dir.'/'.$this->date.'.log';
            } else {
                throw new \Exception(__CLASS__.": require \$config['dir']");
            }
        } else {
            if (isset($config['file'])) {
                $this->log_file = $config['file'];
            } else {
                throw new \Exception(__CLASS__.": require \$config[file]");
            }
        }

        //自动创建目录
        $dir = dirname($this->log_file);
        if (file_exists($dir)) {
            if (!is_writeable($dir) && !chmod($dir, 0777)) {
                throw new \Exception(__CLASS__.": {$dir} unwriteable.");
            }
        } elseif (mkdir($dir, 0777, true) === false) {
            throw new \Exception(__CLASS__.": mkdir dir {$dir} fail.");
        }

        $this->fp = $this->openFile($this->log_file);

        parent::__construct($config);
    }

    public function format($msg, $level, &$date = null)
    {
        $level = self::convert($level);
        if ($level < $this->level_line) {
            return false;
        }
        $level_str = self::$level_str[$level];

        $now = new \DateTime('now');
        $date = $now->format('Ymd');
        $log = $now->format(self::$date_format)."\t{$level_str}\t{$msg}";
        if ($this->verbose) {
            $debug_info = debug_backtrace();
            $file = isset($debug_info[1]['file']) ? $debug_info[1]['file'] : null;
            $line = isset($debug_info[1]['line']) ? $debug_info[1]['line'] : null;

            if ($file && $line) {
                $log .= "\t{$file}\t{$line}";
            }
        }
        $log .= "\n";

        return $log;
    }

    /**
     * 写入日志队列
     * @param $msg  string 信息
     * @param $level int 事件类型
     * @return bool
     */
    public function put($msg, $level = self::INFO)
    {
        $msg = $this->format($msg, $level, $date);
        if ($msg === false) {
            return false;
        }

        if (!isset($this->queue[$date])) {
            $this->queue[$date] = array();
        }
        $this->queue[$date][] = $msg;

        // 如果没有开启缓存，直接将缓冲区的内容写入文件
        // 如果缓冲区内容日志条数达到一定程度，写入文件
        if (count($this->queue, COUNT_RECURSIVE) >= 11
            || $this->enable_cache == false) {
            $this->flush();
        }
    }

    /**
     * 将日志队列（缓冲区）的日志写入文件
     */
    public function flush()
    {
        if (empty($this->queue)) {
            return;
        }

        foreach ($this->queue as $date => $logs) {
            $date = strval($date);
            $log_str = implode('', $logs);

            // 按日期存储日志的情况下，如果日期变化（第二天）
            // 重新设置一下log文件和文件指针
            if ($this->archive && $this->date != $date) {
                fclose($this->fp);
                $this->date = $date;
                $this->log_file = $this->log_dir.'/'.$this->date.'.log';
                $this->fp = $this->openFile($this->log_file);
            }

            //fputs($this->fp, $log_str);
            if ($this->cut_file && filesize($this->log_file) > 209715200) { //200M
                if ($this->archive) {
                    $new_log_file = $this->log_dir.'/'.$this->date.'.log.'.date('His');
                } else {
                    $new_log_file = $this->log_file.".".date('YmdHis');
                }
                fclose($this->fp);
                rename($this->log_file, $new_log_file);
                $this->fp = fopen($this->log_file, 'a+');
            }
            fputs($this->fp, $log_str);
        }

        $this->queue = array();
    }

    private function openFile($file)
    {
        if (!file_exists($file) && touch($file)) {
            $old = umask(0);
            chmod($file, 0777);
            umask($old);
        }

        $fp = fopen($this->log_file, 'a+');

        if (!$fp) {
            throw new \Exception(__CLASS__.": can not open log_file[{$file}].");
        }

        return $fp;
    }

    public function __destruct()
    {
        $this->flush();
    }
}
