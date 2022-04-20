<?php
$app = SPF\App::getInstance();
$configs = $app->config['db'];
if (empty($configs[$php->factory_key])) {
    throw new SPF\Exception\Factory("db->{$app->factory_key} is not found.");
}
$config = $configs[$app->factory_key];

$config['type'] = \SPF\Database::TYPE_COHOOKMYSQL;
if (!empty($config['passwd'])) {
    $config['password'] = $config['passwd'];
    unset($config['passwd']);
}
if (!empty($config['name'])) {
    $config['database'] = $config['name'];
    unset($config['name']);
}

$config['object_id'] = $app->factory_key;
$db = new SPF\Database($config);

return $db;
