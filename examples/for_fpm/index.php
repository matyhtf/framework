<?php
define('DEBUG', 'on');
define('WEBPATH', dirname(dirname(__DIR__)));

require WEBPATH . '/libs/lib_config.php';

//开发环境的配置，如果此目录有配置文件，会优先选择
if(get_cfg_var('env.name') == 'dev')
{
    SPF\App::$app->config->setPath(WEBPATH.'/configs/dev/');
}
SPF\App::$app->handle();

