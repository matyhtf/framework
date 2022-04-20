<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

echo $php->redis->get("key")."\n";
sleep(10);
echo $php->redis->get("key")."\n";;