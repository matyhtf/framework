<?php

if (PHP_OS == 'WINNT') {
    define("NL", "\r\n");
} else {
    define("NL", "\n");
}
define("BL", "<br />" . NL);

/**
 * 生产一个model接口，模型在注册树上为单例
 * @param $model_name
 * @param $db_key
 * @return SPF\Model
 * @throws \SPF\Error
 */
function model($model_name, $db_key = 'master')
{
    return SPF\App::getInstance()->modelLoader->loadModel($model_name, $db_key);
}

/**
 * 传入一个数据库表，返回一个封装此表的Model接口
 * @param $table_name
 * @param $db_key
 * @return SPF\Model
 */
function table($table_name, $db_key = 'master')
{
    return SPF\App::getInstance()->modelLoader->loadTable($table_name, $db_key);
}

/**
 * 开启会话
 * @param bool $readonly
 * @throws \SPF\SessionException
 */
function session($readonly = false)
{
    SPF\App::getInstance()->session->start($readonly);
}

/**
 * 调试数据，终止程序的运行
 */
function debug()
{
    $vars = func_get_args();
    foreach ($vars as $var) {
        if (php_sapi_name() == 'cli') {
            var_export($var);
        } else {
            highlight_string("<?php\n" . var_export($var, true));
            echo '<hr />';
        }
    }
    exit;
}

/**
 * 引发一个错误
 * @param $error_id
 * @param $stop
 */
function error($error_id, $stop = true)
{
    $php = SPF\App::getInstance();;
    $error = new \SPF\Error($error_id);
    if (isset($php->error_call[$error_id])) {
        call_user_func($php->error_call[$error_id], $error);
    } elseif ($stop) {
        exit($error);
    } else {
        echo $error;
    }
}

/**
 * 错误信息输出处理
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 */
function swoole_error_handler($errno, $errstr, $errfile, $errline)
{
    $info = '';
    switch ($errno) {
        case E_USER_ERROR:
            $level = 'User Error';
            break;
        case E_USER_WARNING:
            $level = 'Warnning';
            break;
        case E_USER_NOTICE:
            $level = 'Notice';
            break;
        default:
            $level = 'Unknow';
            break;
    }

    $title = 'SPF ' . $level;
    $info .= '<b>File:</b> ' . $errfile . "<br />\n";
    $info .= '<b>Line:</b> ' . $errline . "<br />\n";
    $info .= '<b>Info:</b> ' . $errstr . "<br />\n";
    $info .= '<b>Code:</b> ' . $errno . "<br />\n";
    echo SPF\Error::info($title, $info);
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        return SPF\App::getInstance()->getEnv($key, $default);
    }
}
