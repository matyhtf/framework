<?php
namespace SPF;

/**
 * Class Log
 * @package SPF
 * @method info
 * @method notice
 * @method warn
 * @method error
 * @method trace
 */
abstract class Log
{
    protected $level_line;
    protected $config;

    const TRACE   = 0;
    const INFO    = 1;
    const NOTICE  = 2;
    const WARN    = 3;
    const ERROR   = 4;

    protected static $level_code = array(
        'TRACE' => 0,
        'INFO' => 1,
        'NOTICE' => 2,
        'WARN' => 3,
        'ERROR' => 4,
    );

    protected static $level_str = array(
        'TRACE',
        'INFO',
        'NOTICE',
        'WARN',
        'ERROR',
    );

    public static $date_format = '[Y-m-d H:i:s]';

    public static function convert($level)
    {
        if (!is_numeric($level)) {
            $level = self::$level_code[strtoupper($level)];
        }
        return $level;
    }

    public function __call($func, $param)
    {
        $this->put($param[0], $func);
    }

    public function setLevel($level = self::TRACE)
    {
        $this->level_line = $level;
    }

    public function __construct($config)
    {
        if (isset($config['level'])) {
            $this->setLevel(intval($config['level']));
        }
        $this->config = $config;
    }

    public function format($msg, $level)
    {
        $level = self::convert($level);
        if ($level < $this->level_line) {
            return false;
        }
        $level_str = self::$level_str[$level];
        return date(self::$date_format)."\t{$level_str}\t{$msg}\n";
    }

    public function flush()
    {
    }
}
