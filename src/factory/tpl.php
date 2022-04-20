<?php
$tpl = new SPF\Template();
$app = SPF\App::getInstance();
$tpl->assign_by_ref('php', $app->env);

$dir = $app->app_path . '/templates';
if (!is_dir($dir)) {
    throw new RuntimeException("templates dir['$dir'] not found.");
}
$tpl->template_dir = $dir;
$tpl->compile_check = SPF\App::$debug;
return $tpl;