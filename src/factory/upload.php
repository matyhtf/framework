<?php
$app = SPF\App::getInstance();
if (empty($app->config['upload'])) {
    throw new Exception("require upload config");
} else {
    $config = $app->config['upload'];
}
$upload = new SPF\Upload($config);
return $upload;
