<?php
$app = SPF\App::getInstance();

if ($app->factory_key == 'master') {
    $config = $app->config['mongo']['master'];

    if (empty($config['host'])) {
        $config['host'] = '127.0.0.1';
    }
} else {
    $config = $app->config['mongo'][$app->factory_key];
    if (empty($config) or empty($config['host'])) {
        throw new Exception("mongodb require server host ip.");
    }
}

if (empty($config['port'])) {
    $config['port'] = 27017;
}
if (!isset($config['option'])) {
    $config['option'] = array();
}

$url = "mongodb://{$config['host']}:{$config['port']}";
$mongo = new MongoClient($url, $config['option']);
return $mongo;
