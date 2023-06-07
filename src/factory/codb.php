<?php
$app = SPF\App::getInstance();
if (empty($app->config['db'][$app->factory_key])) {
    throw new SPF\Exception\Factory("codb->{$app->factory_key} is not found.");
}
$codb = new SPF\Client\CoMySQL($app->factory_key);
return $codb;
