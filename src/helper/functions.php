<?php
define("BL", "<br />" . PHP_EOL);

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

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        return SPF\App::getInstance()->getEnv($key, $default);
    }
}

if (!function_exists('config')) {
    function config($key)
    {
        $app = SPF\App::getInstance();
        $keys = explode('.', $key);
        $value = $app->config;
        foreach ($keys as $name) {
            if (!isset($value[$name])) {
                return null;
            }
            $value = $value[$name];
        }
        return $value;
    }
}

function str_i_starts_with($haystack, $needle)
{
    return strcasecmp(substr($haystack, 0, strlen($needle)), $needle) === 0;
}
