<?php
namespace SPF;

/**
 * 过滤类
 * 用于过滤过外部输入的数据，过滤数组或者变量中的不安全字符，以及HTML标签
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage request_filter
 */
class Filter
{
    public static $error_url;
    public static $origin_get;
    public static $origin_post;
    public static $origin_cookie;
    public static $origin_request;
    public $mode;

    public function __construct($mode = 'deny', $error_url = false)
    {
        $this->mode = $mode;
        self::$error_url = $error_url;
    }

    public function post($param)
    {
        $this->_check($_POST, $param);
    }

    public function get($param)
    {
        $this->_check($_GET, $param);
    }

    public function cookie($param)
    {
        $this->_check($_COOKIE, $param);
    }

    /**
     * 根据提供的参数对数据进行检查
     * @param $data
     * @param $param
     * @return string
     */
    public function _check(&$data, $param)
    {
        foreach ($param as $k => $p) {
            if (!isset($data[$k])) {
                if (isset($p['require']) and $p['require']) {
                    self::raise('param require');
                } else {
                    continue;
                }
            }

            if (isset($p['type'])) {
                $data[$k] = Validate::$p['type']($data[$k]);
                if ($data[$k] === false) {
                    self::raise();
                }

                //最小值参数
                if (isset($p['min']) and is_numeric($data[$k]) and $data[$k] < $p['min']) {
                    self::raise('num too small');
                }
                //最大值参数
                if (isset($p['max']) and is_numeric($data[$k]) and $data[$k] > $p['max']) {
                    self::raise('num too big');
                }

                //最小值参数
                if (isset($p['short']) and is_string($data[$k]) and mb_strlen($data[$k]) < $p['short']) {
                    self::raise('string too short');
                }
                //最大值参数
                if (isset($p['long']) and is_string($data[$k]) and mb_strlen($data[$k]) > $p['long']) {
                    self::raise('string too long');
                }

                //自定义的正则表达式
                if ($p['type'] == 'regx' and isset($p['regx']) and preg_match($p['regx'], $data[$k]) === false) {
                    self::raise();
                }
            }
        }
        //如果为拒绝模式，所有不在过滤参数$param中的键值都将被删除
        if ($this->mode == 'deny') {
            $allow = array_keys($param);
            $have = array_keys($data);
            foreach ($have as $ha) {
                if (!in_array($ha, $allow)) {
                    unset($data[$ha]);
                }
            }
        }
    }

    public static function raise($text = false)
    {
        if (self::$error_url) {
            App::getInstance()->http->redirect(self::$error_url);
        }
        if ($text) {
            exit($text);
        } else {
            exit('Client input param error!');
        }
    }

    /**
     * 过滤$_GET $_POST $_REQUEST $_COOKIE
     */
    public static function request()
    {
        self::$origin_get = $_GET;
        self::$origin_post = $_POST;
        self::$origin_request = $_REQUEST;
        self::$origin_cookie = $_COOKIE;

        $_POST = Filter::filterArray($_POST);
        $_GET = Filter::filterArray($_GET);
        $_REQUEST = Filter::filterArray($_REQUEST);
        $_COOKIE = Filter::filterArray($_COOKIE);
    }

    public static function safe(&$content)
    {
        $content = stripslashes($content);
        $content = html_entity_decode($content, ENT_QUOTES, App::$charset);
    }

    /**
     * 类型转换
     * @param $var
     * @param $type
     * @return bool|float|int|string
     */
    public static function filterVar($var, $type)
    {
        switch ($type) {
            case 'int':
                return intval($var);
            case 'string':
                return htmlspecialchars(strval($var), ENT_QUOTES);
            case 'float':
                return floatval($var);
            default:
                return false;
        }
    }

    /**
     * 过滤数组
     * @param $array
     * @return array
     */
    public static function filterArray($array)
    {
        if (!is_array($array)) {
            return false;
        }

        $clean = array();
        foreach ($array as $key => $string) {
            if (is_array($string)) {
                self::filterArray($string);
            } else {
                $string = self::escape($string);
                $key = self::escape($key);
            }
            $clean[$key] = $string;
        }
        return $clean;
    }

    /**
     * 使输入的代码安全
     * @param $string
     * @return string
     */
    public static function escape($string)
    {
        if (is_numeric($string)) {
            return $string;
        }
        //HTML转义
        $string = htmlspecialchars($string, ENT_QUOTES, App::$charset);
        //启用了magic_quotes
        $magic_exists = function_exists('get_magic_quotes_gpc');
        if (version_compare(PHP_VERSION, '7.0.0', '>') ||
            !$magic_exists ||
            ($magic_exists && !\get_magic_quotes_gpc())) {
            $string = addslashes($string);
        }
        return $string;
    }
}
