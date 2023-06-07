<?php
$php = SPF\App::getInstance();;
$config = $php->config['log'];
if (empty($config[$php->factory_key])) {
    throw new SPF\Exception\Factory("log->{$php->factory_key} is not found.");
}
$conf = $config[$php->factory_key];
if (empty($conf['type'])) {
    $conf['type'] = 'EchoLog';
}
$class = 'SPF\\Log\\' . $conf['type'];
$log = new $class($conf);
if (!empty($conf['level'])) {
    $log->setLevel($conf['level']);
}
return $log;
