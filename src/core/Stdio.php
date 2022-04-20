<?php
namespace SPF;
/**
 * 标准输入和输出
 * @author htf
 */
class Stdio
{
    static $in;
    static $out;
    static $buffer_size = 1024;

    static function input($h = '')
    {
        if (!self::$in)
        {
            self::$in = fopen('php://stdin', 'r');
        }
        if ($h)
        {
            self::output($h);
        }
        return trim(fread(self::$in, self::$buffer_size));
    }

    static function output($string)
    {
        if (!self::$out)
        {
            self::$out = fopen('php://stdout', 'w');
        }
        return fwrite(self::$out, $string);
    }
}
