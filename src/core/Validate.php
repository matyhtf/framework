<?php
namespace SPF;

/**
 * 数值验证类，类中的方法都是静态的，用于检测一个变量是否符合某种规则，不符合返回false，符合返回原值
 * @author tianfeng.han
 * @package SwooleSystem
 * @subpackage Validate
 * @link http://www.swoole.com/
 */
class Validate
{
    public static $regx = array(
        //邮箱
        'email' => '/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix',
        //手机号码
        'mobile' => '/^\d{11}$/',
        //固定电话带分机号
        'tel' => '/^((0\d{2,3})-)(\d{7,8})(-(\d{1,4}))?$/',
        //固定电话不带分机号
        'phone' => '/^\d{3}-?\d{8}|\d{4}-?\d{7}$/',
        //域名
        'domain' => '/@([0-9a-z-_]+.)+[0-9a-z-_]+$/i',
        //日期
        'date' => '/^[1-9][0-9][0-9][0-9]-[0-9]{1,2}-[0-9]{1,2}$/',
        //日期时间
        'datetime' => '/^[1-9][0-9][0-9][0-9]-[0-9]{1,2}-[0-9]{1,2} [0-9]{1,2}(:[0-9]{1,2}){1,2}$/',
        //时间
        'time' => '/^[0-9]{1,2}(:[0-9]{1,2}){1,2}$/',
        /*--------- 数字类型 --------------*/
        'int'=>'/^\d{1,11}$/', //十进制整数
        'hex'=>'/^0x[0-9a-f]+$/i', //16进制整数
        'bin'=>'/^[01]+$/', //二进制
        'oct' => '/^0[1-7]*[0-7]+$/', //8进制
        'float' => '/^\d+\.[0-9]+$/', //浮点型
        /*---------字符串类型 --------------*/
        //utf-8中文字符串
        'chinese' => '/^[\x{4e00}-\x{9fa5}]+$/u',
        /*---------常用类型 --------------*/
        'english' => '/^[a-z0-9_\.]+$/i', //英文
        'nickname' => '/^[\x{4e00}-\x{9fa5}a-z_\.]+$/ui', //昵称，可以带英文字符和数字
        'realname' => '/^[\x{4e00}-\x{9fa5}]+$/u', //真实姓名
        'password' => '/^[a-z0-9]{6,32}$/i', //密码
        'area' => '/^0\d{2,3}$/', //区号
        'version' => '/^\d+\.\d+\.\d+$/',       //版本号
        'url' => '((https?)://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)', //URL
    );
    /**
     * 正则验证
     * @param $regx
     * @param $input
     * @return bool|string
     */
    public static function regx($regx, $input)
    {
        $n = preg_match($regx, $input, $match);
        if ($n === 0) {
            return false;
        } else {
            return $match[0];
        }
    }

    public static function isVersion($ver)
    {
        return self::check('version', $ver);
    }

    public static function check($ctype, $input)
    {
        if (isset(self::$regx[$ctype])) {
            return self::regx(self::$regx[$ctype], $input);
        } else {
            return self::$ctype($input);
        }
    }

    /**
     * 检查数组是否缺少某些Key
     * @param array $array
     * @param array $keys
     *
     * @return bool
     */
    public static function checkLacks(array $array, array $keys)
    {
        foreach ($keys as $k) {
            if (empty($array[$k])) {
                return false;
            }
        }
        return true;
    }

    /**
     * 验证字符串格式
     * @param $str
     * @return false or $str
     */
    public static function string($str)
    {
        return filter_var($str, FILTER_DEFAULT);
    }
    /**
     * 验证是否为URL
     * @param $str
     * @return false or $str
     */
    public static function url($str)
    {
        return filter_var($str, FILTER_VALIDATE_URL);
    }
    /**
     * 过滤HTML，使参数为纯文本
     * @param $str
     * @return false or $str
     */
    public static function text($str)
    {
        return filter_var($str, FILTER_SANITIZE_STRING);
    }
    /**
     * 检测是否为gb2312中文字符串
     * @param $str
     * @return false or $str
     */
    public static function chinese_gb($str)
    {
        $n =  preg_match("/^[".chr(0xa1)."-".chr(0xff)."]+$/", $str, $match);
        if ($n===0) {
            return false;
        } else {
            return $match[0];
        }
    }
    /**
     * 检测是否为自然字符串（可是中文，字符串，下划线，数字），不包含特殊字符串，只支持utf-8或者gb2312
     * @param $str
     * @return false or $str
     */
    public static function realstring($str, $encode='utf8')
    {
        if ($encode=='utf8') {
            $n = preg_match('/^[\x{4e00}-\x{9fa5}|a-z|0-9|A-Z]+$/u', $str, $match);
        } else {
            $n = preg_match("/^[".chr(0xa1)."-".chr(0xff)."|a-z|0-9|A-Z]+$/", $str, $match);
        }
        if ($n===0) {
            return false;
        } else {
            return $match[0];
        }
    }
    /**
     * 检测是否一个英文单词，不含空格和其他特殊字符
     * @param $str
     * @return false or $str
     */
    public static function word($str, $other='')
    {
        $n = preg_match("/^([a-zA-Z_{$other}]*)$/", $str, $match);
        if ($n===0) {
            return false;
        } else {
            return $match[0];
        }
    }

    /**
     * 检查是否ASSIC码
     * @param $value
     * @return true or false
     */
    public static function assic($value)
    {
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $ord = ord(substr($value, $i, 1));
            if ($ord > 127) {
                return false;
            }
        }
        return $value;
    }

    /**
     * IP地址
     * @param $value
     * @return bool
     */
    public static function ip($value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 检查值如果为空则设置为默认值
     * @param $value
     * @param $default
     * @return unknown_type
     */
    public static function value_default($value, $default)
    {
        if (empty($value)) {
            return $default;
        } else {
            return $value;
        }
    }
}
