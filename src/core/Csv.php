<?php
namespace SPF;

/**
 * 一种简单的数据格式
 * Class Csv
 *
 * @package SPF
 */
class Csv
{
    public static $row_sep = "\n";
    public static $col_sep = ",";
    public static $data_sep = ':';

    public $data;
    public $text;

    /**
     * 设置3种分隔符
     *
     * @param string $row_sep
     * @param string $col_sep
     * @param string $data_sep
     */
    public static function set_sep($row_sep = "\n", $col_sep = ",", $data_sep = ':')
    {
        self::$row_sep = $row_sep;
        self::$col_sep = $col_sep;
        self::$data_sep = $data_sep;
    }

    /**
     * 解析一行
     * @param $line
     * @return array
     */
    public static function parse_line($line)
    {
        $line = trim($line);
        $result = array();
        $datas = explode(self::$col_sep, $line);
        if (empty(self::$data_sep)) {
            return $datas;
        }
        foreach ($datas as $data) {
            $d = self::parse_data($data);
            if (empty($d[0])) {
                continue;
            }
            $result[trim($d[0])] = trim($d[1]);
        }
        return $result;
    }

    public static function parse_data($data)
    {
        if (self::$data_sep) {
            $data = trim($data);
            return explode(self::$data_sep, $data);
        }
    }

    /**
     * 分割一段文字
     *
     * @return unknown_type
     */
    public static function parse_text($text)
    {
        $text = trim($text);
        $result = array();
        $lines = explode(self::$row_sep, $text);
        foreach ($lines as $line) {
            $result[] = self::parse_line($line);
        }
        return $result;
    }

    /**
     * 解析函数格式，类似于 Max(a,b)
     *
     * @return unknown_type
     */
    public static function parse_func($str, $param)
    {
        $str = trim($str);
        $_func = explode('(', $str, 2);

        //不是函数形式的，返回false
        if (empty($_func)) {
            return false;
        }

        //实际要调用的函数名称
        $func = $_func[0];
        $func_arg = explode(';', substr($_func[1], 0, strlen($_func[1]) - 1));

        //实际要传的参数
        $arg = array();
        foreach ($func_arg as $a) {
            if (isset($param[$a])) {
                $arg[] = $param[$a];
            } else {
                $arg[] = null;
            }
        }
        return call_user_func($func, $arg);
    }

    /**
     * 构建行
     *
     * @param $array
     *
     * @return string
     */
    public static function build_line($array)
    {
        if (self::$data_sep) {
            $line = '';
            foreach ($array as $k => $v) {
                $line .= $k . self::$data_sep . $v . self::$col_sep;
            }
            return rtrim($line, self::$col_sep);
        } else {
            return implode(self::$col_sep, $array);
        }
    }

    /**
     * CSV格式字符串转为数组
     *
     * @param $str
     * @param $line_keys
     *
     * @return array
     */
    public static function str2array($str, $line_keys = null)
    {
        //切分成多行
        $lines = explode(self::$row_sep, $str);
        $result = array();
        foreach ($lines as $li) {
            $li = trim($li);
            if (!empty($li)) {
                if (is_array($line_keys)) {
                    //切分成多列
                    $data = self::parse_line($li);
                    $tmp = array();
                    foreach ($line_keys as $index => $key) {
                        $tmp[$key] = $data[$index];
                    }
                    $result[] = $tmp;
                } else {
                    $result[] = self::parse_line($li);
                }
            }
        }
        return $result;
    }

    /**
     * 数组转为CSV字符串
     *
     * @param $array
     *
     * @return string
     */
    public static function array2str($array)
    {
        $str = '';
        foreach ($array as $li) {
            $str .= self::build_line($li) . self::$row_sep;
        }
        return $str;
    }
}
