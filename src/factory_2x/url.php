<?php
$app = SPF\App::getInstance();
$config = $app->config['url'][$app->factory_key];
return new SPF\URL($config);