<?php
$app = SPF\App::getInstance();
$configs = $app->config['cache'];
if (empty($configs[$app->factory_key])) {
    throw new SPF\Exception\Factory("cache->" . $app->factory_key . " is not found.");
}
return SPF\Factory::getCache($app->factory_key);
