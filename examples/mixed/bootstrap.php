<?php
define('DEBUG', 'on');
require __DIR__ . '/../vendor/autoload.php';
\SPF\App::getInstance(realpath(__DIR__ . '/..'));

function start_websocket_server()
{
    $descriptorspec = array(
        0 => array("pipe", "r"),
        1 => array("pipe", "w"),
        2 => array("file", "/dev/null", "a"),
    );
    $proc = proc_open('/usr/bin/env php ' . __DIR__ . '/../server/websocket.php', $descriptorspec, $pipes);
    while (true) {
        $line = fgets($pipes[1]);
        if (feof($pipes[1])) {
            trigger_error("Unable to start server");
            break;
        }
        if (strpos($line, 'running on 0.0.0.0:9501') !== false) {
            break;
        }
    }
    proc_close($proc);
}