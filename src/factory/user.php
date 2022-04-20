<?php
$app = SPF\App::getInstance();
$user = new SPF\Auth($app->config['user']);
return $user;
