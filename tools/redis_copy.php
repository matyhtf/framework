<?php
/**
 * 此工具主要是从Redis的AOF备份文件的数据同步到另外一台Redis机器
 * 支持热同步，数据复制完毕后，来源Redis如果有写入，仍然会继续同步到新服务器（必须关闭Redis的AOF压缩特性）
 * 暂时不支持断点续传，请勿强杀此进程
 */
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

if (empty($argv[2]))
{
    echo "php redis_copy.php [aof_file] [dst redis server].\n";
    echo "etc: php redis_copy.php /data/redis_new_slave/appendonly.aof tcp://192.168.1.73:6001\n";
    die;
}

$aof_file = $argv[1];
$redis_server = $argv[2];
if (!is_file($aof_file))
{
    echo "redis aof file[$aof_file] not exist.\n";
}
SPF\Component\Redis::syncFromAof($aof_file, $redis_server);