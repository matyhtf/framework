<?php
namespace SPF;
/**
 * 附加工具集合
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage tools
 */
class Tool
{
    static public $url_key_join = '=';
    static public $url_param_join = '&';
    static public $url_prefix = '';
    static public $url_add_end = '';

    const DATE_FORMAT_HTTP   = 'D, d-M-Y H:i:s T';
    const WEEK_TWO = '周';
    const WEEK_THREE = '星期';

    const DATE_FORMAT_HUMEN = 'Y-m-d H:i:s';

    static $number = array('〇','一','二','三','四','五','六','七','八','九');

    /**
     * 数字转星期
     * @param $num
     * @param bool $two
     * @return string
     */
    static function num2week($num, $two = true)
    {
        if ($num == '6')
        {
            $num = '日';
        }
        else
        {
            $num = Tool::num2han($num + 1);
        }
        if ($two)
        {
            return self::WEEK_TWO . $num;
        }
        else
        {
            return self::WEEK_THREE . $num;
        }
    }

    /**
     * 数字转为汉字
     * @param $num_str
     * @return mixed
     */
    static function num2han($num_str)
    {
        return str_replace(range(0,9),self::$number,$num_str);
    }

    static function scandir($dir)
    {
        if (function_exists('scandir'))
        {
            $files = scandir($dir);
            foreach ($files as $key => $value)
            {
                if ($value == '.' or $value == '..')
                {
                    unset($files[$key]);
                }
            }
            return array_values($files);
        }
        else
        {
            $dh  = opendir($dir);
            while (false !== ($filename = readdir($dh)))
            {
                if ($filename == '.' or $filename == '..')
                {
                    continue;
                }
                $files[] = $filename;
            }
            sort($files);
            return $files;
        }
    }

    /**
     * 将PHP变量到处为文件内容
     * @param mixed $var
     * @return string
     */
    static function export($var)
    {
        return "<?php\nreturn ".var_export($var, true).";";
    }

    /**
     * 加锁读取文件
     * @param $file
     * @param bool $exclusive
     * @return bool|string
     */
    static function readFile($file, $exclusive = false)
    {
        $fp = fopen($file, 'r');
        if (!$fp)
        {
            return false;
        }
        $lockType = $exclusive ? LOCK_EX : LOCK_SH;
        if (flock($fp, $lockType) === false)
        {
            fclose($fp);
        }
        $content = '';
        while(!feof($fp))
        {
            $content .= fread($fp, 8192);
        }
        flock($fp, LOCK_UN);
        return $content;
    }

    /**
     * 获取字符串最后一位
     * @param $string
     * @return mixed
     */
    static function endchar($string)
    {
        return $string[strlen($string) - 1];
    }

    /**
     * 解析URI
     * @param string $url
     * @return array $return
     */
    static public function uri($url)
    {
        $res = parse_url($url);
        $return['protocol'] = $res['scheme'];
        $return['host'] = $res['host'];
        $return['port'] = $res['port'];
        $return['user'] = $res['user'];
        $return['pass'] = $res['pass'];
        $return['path'] = $res['path'];
        $return['id'] = $res['fragment'];
        parse_str($res['query'], $return['params']);
        return $return;
    }

    static function httpExpire($lastModifyTime, $expire = 1800)
    {
        $expire = intval($expire);
        $responseTime = $requestTime = $_SERVER['REQUEST_TIME'];
        $result = true;

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
        {
            $lastModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            //命中本地缓存
            if ($lastModifiedSince > $lastModifyTime)
            {
                App::getInstance()->http->status(304);
                $result = false;
            }
        }

        $headers = array(
            'Cache-Control' => "max-age={$expire}", // HTTP 1.1
            'Pragma' => "max-age={$expire}", // HTTP 1.0
            'Last-Modified' => date(self::DATE_FORMAT_HTTP, $lastModifyTime),
            'Expires' => date(self::DATE_FORMAT_HTTP, $responseTime + $expire),
        );

        foreach ($headers as $key => $value)
        {
            App::getInstance()->http->header($key, $value);
        }
        return $result;
    }

    /**
     * 多久之前
     * @param $datetime
     * @return string
     */
    static function howLongAgo($datetime)
    {
        $timestamp = strtotime($datetime);
        $seconds = time();

        $time = date('Y', $seconds) - date('Y', $timestamp);
        if ($time > 0)
        {
            if ($time == 1)
            {
                return '去年';
            }
            else
            {
                return $time . '年前';
            }
        }

        $time = date('m', $seconds) - date('m', $timestamp);
        if ($time > 0)
        {
            if ($time == 1)
            {
                return '上月';
            }
            else
            {
                return $time . '个月前';
            }
        }
        $time = date('d', $seconds) - date('d', $timestamp);
        if ($time > 0)
        {
            if ($time == 1)
            {
                return '昨天';
            }
            elseif ($time == 2)
            {
                return '前天';
            }
            else
            {
                return $time . '天前';
            }
        }

        $time = date('H', $seconds) - date('H', $timestamp);
        if ($time >= 1)
        {
            return $time . '小时前';
        }

        $time = date('i', $seconds) - date('i', $timestamp);
        if ($time >= 1)
        {
            return $time . '分钟前';
        }

        $time = date('s', $seconds) - date('s', $timestamp);
        return $time . '秒前';
    }

    /**
     * 合并URL字串，parse_query的反向函数
     * @param $urls
     * @return string
     */
    static function combine_query($urls)
    {
        $url = array();
        foreach ($urls as $k => $v)
        {
            if (!empty($k))
            {
                $url[] = $k . self::$url_key_join . urlencode($v);
            }
        }
        return implode(self::$url_param_join, $url);
    }

    static function urlAppend($url, $array)
    {
        if (strpos($url, '?') === false)
        {
            return $url . '?' . Http::buildQuery($array);
        }
        else
        {
            return $url . '&' . Http::buildQuery($array);
        }
    }

    /**
     * URL合并
     * @param $key
     * @param $value
     * @param $ignore
     * @return string
     */
    static function url_merge($key, $value, $ignore = null, $urls = null)
    {
        $url = array();
        if ($urls === null) $urls = $_GET;

        $urls = array_merge($urls, array_combine(explode(',', $key), explode(',', $value)));
        if ($ignore !== null)
        {
            $ignores = explode(',', $ignore);
            foreach ($ignores as $ig)
            {
                unset($urls[$ig]);
            }
        }
        if (self::$url_prefix == '')
        {
            $qm = strpos($_SERVER['REQUEST_URI'], '?');
            if ($qm !== false)
            {
                $prefix = substr($_SERVER['REQUEST_URI'], 0, $qm + 1);
            }
            else
            {
                $prefix = $_SERVER['REQUEST_URI'] . '?';
            }
        }
        else
        {
            $prefix = self::$url_prefix;
        }
        return $prefix . http_build_query($urls) . self::$url_add_end;
    }

    /**
     * URL解析到REQUEST
     * @param $url
     * @param $request
     * @return unknown_type
     */
    static function url_parse_into($url, &$request)
    {
        $url = str_replace(self::$url_add_end, '', $url);
        if (self::$url_key_join == self::$url_param_join)
        {
            $urls = explode(self::$url_param_join, $url);
            $c = intval(count($urls) / 2);
            for ($i = 0; $i < $c; $i++)
            {
                $request[$urls[$i * 2]] = $urls[$i * 2 + 1];
            }
        }
        else
        {
            $urls = explode(self::$url_param_join, $url);
            foreach ($urls as $u)
            {
                $us = explode(self::$url_key_join, $u);
                $request[$us[0]] = $us[1];
            }
        }
    }

    /**
     * 数组编码转换
     * @param $in_charset
     * @param $out_charset
     * @param $data
     * @return $data
     */
    static function array_iconv($in_charset, $out_charset, $data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) $value = self::array_iconv($in_charset, $out_charset, $value);
                else $value = iconv($in_charset, $out_charset, $value);
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * 数组饱满度
     * @param $array
     * @return unknown_type
     */
    static function array_fullness($array)
    {
        $nulls = 0;
        foreach ($array as $v) if (empty($v) or intval($v) < 0) $nulls++;
        return 100 - intval($nulls / count($array) * 100);
    }

    /**
     * 根据生日中的月份和日期来计算所属星座*
     * @param int $birth_month
     * @param int $birth_date
     * @return string
     */
    static function get_constellation($birth_month, $birth_date)
    {
        //判断的时候，为避免出现1和true的疑惑，或是判断语句始终为真的问题，这里统一处理成字符串形式
        $birth_month = strval($birth_month);
        $constellation_name = array('水瓶座', '双鱼座', '白羊座', '金牛座', '双子座', '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座');
        if ($birth_date <= 22)
        {
            if ('1' !== $birth_month)
            {
                $constellation = $constellation_name[$birth_month - 2];
            }
            else
            {
                $constellation = $constellation_name[11];
            }
        }
        else
        {
            $constellation = $constellation_name[$birth_month - 1];
        }
        return $constellation;
    }

    /**
     * 根据生日中的年份来计算所属生肖
     *
     * @param int $birth_year
     * @return string
     */
    static function get_animal($birth_year, $format = '1')
    {
        //1900年是子鼠年
        if ($format == '2')
        {
            $animal = array('子鼠', '丑牛', '寅虎', '卯兔', '辰龙', '巳蛇', '午马', '未羊', '申猴', '酉鸡', '戌狗', '亥猪');
        }
        elseif ($format == '1')
        {
            $animal = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
        }
        $my_animal = ($birth_year - 1900) % 12;
        return $animal[$my_animal];
    }

    /**
     * 根据生日来计算年龄
     *
     * 用Unix时间戳计算是最准确的，但不太好处理1970年之前出生的情况
     * 而且还要考虑闰年的问题，所以就暂时放弃这种方式的开发，保留思想
     *
     * @param int $birth_year
     * @param int $birth_month
     * @param int $birth_date
     * @return int
     */
    static function get_age($birth_year, $birth_month, $birth_date)
    {
        $now_age = 1; //实际年龄，以出生时为1岁计
        $full_age = 0; //周岁，该变量放着，根据具体情况可以随时修改
        $now_year = date('Y', time());
        $now_date_num = date('z', time()); //该年份中的第几天
        $birth_date_num = date('z', mktime(0, 0, 0, $birth_month, $birth_date, $birth_year));
        $difference = $now_date_num - $birth_date_num;

        if ($difference > 0)
        {
            $full_age = $now_year - $birth_year;
        }
        else
        {
            $full_age = $now_year - $birth_year - 1;
        }
        $now_age = $full_age + 1;
        return $now_age;
    }

    /**
     * 发送一个UDP包
     * @return unknown_type
     */
    static function sendUDP($server_ip, $server_port, $data, $timeout = 30)
    {
        $client = stream_socket_client("udp://$server_ip:$server_port", $errno, $errstr, $timeout);
        if (!$client)
        {
            echo "ERROR: $errno - $errstr<br />\n";
        }
        else
        {
            fwrite($client, $data);
            fclose($client);
        }
    }

    /**
     * 复制目录
     * @param $fdir源目录名(不带/)
     * @param $tdir目标目录名(不带/)
     * @return
     */
    static function dir_copy($fdir, $tdir)
    {
        if (is_dir($fdir)) {
            if (!is_dir($tdir)) {
                mkdir($tdir);
            }
            $handle = opendir($fdir);
            while (false !== ($filename = readdir($handle))) {
                if ($filename != "." && $filename != "..") self::dir_copy($fdir . "/" . $filename, $tdir . "/" . $filename);
            }
            closedir($handle);
            return true;
        } else {
            copy($fdir, $tdir);
            return true;
        }
    }

    static function fileAppend($log, $file = '')
    {
        if (empty($file))
        {
            $file = '/tmp/swoole.log';
        }
        if (!is_string($log))
        {
            $log = var_export($log, true);
        }
        if (self::endchar($log) !== "\n")
        {
            $log .= "\n";
        }
        file_put_contents($file, $log, FILE_APPEND);
    }

    /**
     * @param $n
     * @param int $round
     * @return string
     */
    static function getHumanSize($n, $round = 3)
    {
        if ($n > 1024 * 1024 * 1024)
        {
            return round($n / (1024 * 1024 * 1024), $round) . "G";
        }
        elseif ($n > 1024 * 1024)
        {
            return round($n / (1024 * 1024), $round) . "M";
        }
        elseif ($n > 1024)
        {
            return round($n / (1024), $round) . "K";
        }
        else
        {
            return $n;
        }
    }

    /**
     * 将秒数转为人性化格式
     * @param $n
     * @param int $round
     * @return string
     */
    static function getHumanTime($n, $round = 3)
    {
        if ($n > 86400 * 365)
        {
            return round($n / ( 86400 * 365), $round) . "年";
        }
        elseif ($n > 86400 * 30)
        {
            return round($n / (86400 * 30), $round) . "月";
        }
        elseif ($n > 86400)
        {
            return round($n / (86400), $round) . "天";
        }
        elseif ($n > 3600)
        {
            return round($n / (3600), $round) . "小时";
        }
        elseif ($n > 60)
        {
            return round($n / (60), $round) . "分钟";
        }
        else
        {
            return $n."秒";
        }
    }

    /**
     * @param $func
     */
    static function showCost($func)
    {
        $_t = microtime(true);
        $_m = memory_get_usage(true);
        call_user_func($func);
        $t = round((microtime(true) - $_t) * 1000, 3);
        $m = memory_get_usage(true) - $_m;
        echo "cost Time: {$t}ms, Memory=".self::getHumanSize($m)."\n";
    }

    /**
     * 从Server列表中随机选出一个，使用status配置可以实现上线下线管理，weight(0-100)配置权重
     * @param array $servers
     * @return mixed
     */
    static function getServer(array $servers)
    {
        $weight = 0;
        //返回全部有效的节点
        foreach ($servers as $k => $svr)
        {
            $weight += $svr['weight'];
        }
        //计算权重并随机选择一台机器
        $use = rand(0, $weight - 1);
        $weight = 0;
        foreach ($servers as $k => $svr)
        {
            //默认100权重
            if (empty($svr['weight']))
            {
                $svr['weight'] = 100;
            }
            $weight += $svr['weight'];
            //在权重范围内
            if ($use < $weight)
            {
                return $svr;
            }
        }
        //绝不会到这里
        $servers = array_values($servers);
        return $servers[0];
    }

    /**
     * 打印数组
     * @param $var
     * @return mixed
     */
    static function dump($var)
    {
        return highlight_string("<?php\n\$array = ".var_export($var, true).";", true);
    }

    /**
     * @param array $arr
     */
    static function arrayUnique(array &$arr)
    {
        $map = array();
        foreach ($arr as $k => $v)
        {
            if (is_object($v))
            {
                $hash = spl_object_hash($v);
            }
            elseif (is_resource($v))
            {
                $hash = intval($v);
            }
            else
            {
                $hash = $v;
            }
            if (isset($map[$hash]))
            {
                unset($arr[$k]);
            }
            else
            {
                $map[$hash] = true;
            }
        }
    }

    /**
     * @param $array
     * @return \stdClass
     */
    static function array2object($array)
    {
        $object = new \stdClass();
        foreach ($array as $key => $value)
        {
            $object->$key = $value;
        }
        return $object;
    }

    /**
     * @param $object
     * @return array
     */
    static function object2array($object)
    {
        $array = [];
        foreach ($object as $key => $value)
        {
            $array[$key] = $value;
        }
        return $array;
    }

    /**
     * 获取现在的时间字符串，格式为 2016-12-12 00:00:01
     * @param null $tm
     * @return bool|string
     */
    static function now($tm = null)
    {
        return date(self::DATE_FORMAT_HUMEN, $tm);
    }

    /**
     * 检查地址是否未私有
     * @param string $ip
     * @return bool
     */
    static function isPrivateIP(string $ip)
    {
        if (strncmp($ip, "10.", 3) == 0 or strncmp($ip, "192.168.", 8) == 0)
        {
            return true;
        }
        if (strncmp($ip, "172.", 4) == 0)
        {
            $arr = explode('.', $ip);
            if ($arr[1] >= 16 and $arr[1] <= 31)
            {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $data
     * @return string
     */
    static function packJson($data)
    {
        $json = json_encode($data);
        return pack('N', _string($json)->len()) . $json;
    }

    /**
     * @param $str
     * @return mixed
     */
    static function unpackJson($str)
    {
        return json_decode(substr($str, 4), true);
    }
}
