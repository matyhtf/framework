<?php

define('DEBUG', 'on');
require __DIR__ . '/../vendor/autoload.php';

$app = SPF\App::getInstance(__DIR__ . '/../');

$client = new SPF\Client\WebSocket('127.0.0.1', 9443, '/');
if(!$client->connect())
{
    echo "connect to server failed.\n";
    exit;
}
while (true)
{
    $client->send("hello world");
    $message = $client->recv();
    if ($message === false)
    {
        break;
    }
    echo "Received from server: {$message}\n";
    sleep(1);
}
echo "Closed by server.\n";