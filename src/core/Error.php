<?php
namespace SPF;

/**
 * Swoole错误类
 * 错误输出、数据调试、中断程序运行
 * @package SwooleSystem
 * @subpackage Error
 * @author Tianfeng.Han
 *
 */
class Error extends \Exception
{
    /**
     * 错误ID
     * @var int
     */
    public $error_id;

    /**
     * 错误信息
     * @var string
     */
    public $error_msg;
    public static $error_code;
    public static $stop = true;
    public static $echo_html = false;
    public static $display = true;

    /**
     * 错误对象
     * @param $error string 如果为INT，则读取错误信息字典，否则设置错误字符串
     */
    public function __construct($error)
    {
        if (is_numeric($error)) {
            if (empty($this->error_id)) {
                include dirname(__DIR__) . '/data/text/error_code.php';
                //错误ID
                $this->error_id = (int)$error;
                //错误信息
                if (!isset(self::$error_code[$this->error_id])) {
                    $this->error_msg = self::$error_code[$this->error_id];
                    parent::__construct($this->error_msg, $error);
                }
            }
        } else {
            $this->error_id = 0;
            $this->error_msg = $error;
            parent::__construct($error);
        }
        $php = SPF\App::getInstance();
        ;
        //如果定义了错误监听程序
        if (isset($php->error_call[$this->error_id])) {
            call_user_func($php->error_call[$this->error_id], $error);
        }
        if (self::$stop) {
            exit(Error::info('SPF Error', $this->error_msg));
        }
    }

    /**
     * 输出一条错误信息，并结束程序的运行
     * @param $msg
     * @param $content
     * @return string
     */
    public static function info($msg, $content)
    {
        if (!defined('DEBUG') or DEBUG == 'off' or self::$display == false) {
            return false;
        }
        $info = '';

        if (self::$echo_html) {
            $info .= <<<HTMLS
<html>
<head>
<title>$msg</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<style type="text/css">
*{
	font-family:		Consolas, Courier New, Courier, monospace;
	font-size:			14px;
}
body {
	background-color:	#fff;
	margin:				40px;
	color:				#000;
}

#content  {
border:				#999 1px solid;
background-color:	#fff;
padding:			20px 20px 12px 20px;
line-height:160%;
}

h1 {
font-weight:		normal;
font-size:			14px;
color:				#990000;
margin: 			0 0 4px 0;
}
</style>
</head>
<body>
	<div id="content">
		<h1>$msg</h1>
		<p>$content</p><pre>
HTMLS;
        } else {
            $info .= "$msg: $content\n";
        }

        $trace = debug_backtrace();
        $info .= str_repeat('-', 100) . "\n";
        foreach ($trace as $k => $t) {
            if (empty($t['line'])) {
                $t['line'] = 0;
            }
            if (empty($t['class'])) {
                $t['class'] = '';
            }
            if (empty($t['type'])) {
                $t['type'] = '';
            }
            if (empty($t['file'])) {
                $t['file'] = 'unknow';
            }
            $info .= "#$k line:{$t['line']} call:{$t['class']}{$t['type']}{$t['function']}\tfile:{$t['file']}\n";
        }
        $info .= str_repeat('-', 100) . "\n";
        if (self::$echo_html) {
            $info .= '</pre></div></body></html>';
        }
        if (!defined('SWOOLE_SERVER') and self::$stop) {
            exit($info);
        } else {
            return $info;
        }
    }
    public static function warn($title, $content)
    {
        echo '<b>Warning </b>:'.$title."<br/> \n";
        echo $content;
    }
    /**
     * 调试Session
     * @return unknown_type
     */
    public static function sessd()
    {
        echo '<pre>';
        echo '<h1>Session Data:</h1><hr />';
        var_dump($_SESSION);
        echo '<h1>Cookies Data:</h1><hr />';
        var_dump($_COOKIE);
        echo '</pre>';
    }

    public static function reqd()
    {
        echo '<pre>';
        echo '<h1>POST Data:</h1><hr />';
        var_dump($_POST);
        echo '<h1>GET Data:</h1><hr />';
        var_dump($_GET);
    }

    public static function servd()
    {
        echo '<pre>';
        echo '<h1>Server Data:</h1><hr />';
        var_dump($_SERVER);
        echo '<h1>ENV Data:</h1><hr />';
        var_dump($_ENV);
        echo '<h1>REQUEST Data:</h1><hr />';
        var_dump($_REQUEST);
        echo '</pre>';
    }

    public static function debug($var)
    {
        debug($var);
    }
    public static function dump()
    {
        echo '<pre>';
        $vars = func_get_args();
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
    }
    /**
     * 以表格的形式显示一个2维数组
     * @param $var
     * @return unknown_type
     */
    public static function output($var)
    {
        if (!is_array($var)) {
            self::warn('Error Debug!', 'Not is a array!');
        }
        $attr['border'] = 1;
        $attr['style'] = 'font-size:14px';

        $table = new HTML_table($var, $attr);
        echo $table->html();
    }
    public static function parray($array)
    {
        if (!is_array($array)) {
            self::warn('Error Debug!', 'Not is a array!');
        }
        foreach ($array as $k=>$v) {
            echo $k,': ';
            var_dump($v);
            echo BL;
        }
    }
    public static function pecho($str)
    {
        echo $str,"<br />\n";
    }
    /**
     * 调试数据库
     * @return unknown_type
     */
    public static function dbd($bool = true)
    {
        $php = SPF\App::getInstance();
        ;
        if ($bool) {
            $php->db->debug = true;
        } else {
            $php->db->debug = false;
        }
    }
    public static function tpld($bool = true)
    {
        $php = SPF\App::getInstance();
        ;
        if ($bool) {
            $php->tpl->debugging = true;
        } else {
            $php->tpl->debugging = false;
        }
    }
    public function __toString()
    {
        if (!isset(self::$error_code[$this->error_id])) {
            return 'Not defined Error';
        }
        return self::$error_code[$this->error_id];
    }
}
