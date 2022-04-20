<?php
$app = SPF\App::getInstance();
$configs = $app->config['db'];
if (empty($configs[$app->factory_key])) {
    throw new SPF\Exception\Factory("db->{$app->factory_key} is not found.");
}
$config = $configs[$app->factory_key];
if (!empty($config['use_proxy'])) {
    $db = new SPF\Database\Proxy($config);
} else {
    $db = new SPF\Database($config);
    $db->connect();
}
return $db;
