<?php

namespace SPF\Protocol;

use SPF\Queue\File;
use SPF;

class QueueServer implements SPF\IFace\Protocol
{
    /**
     * @var  SPF\IFace\Queue $queue
     */
    protected $queue;
    protected $app;

    const CRLF = "\r\n";

    function __construct($queue)
    {
        $this->queue = $queue;
        $this->app = SPF\App::getInstance();
    }

    function onReceive($server, $client_id, $tid, $data)
    {
        $request = explode(' ', $data, 2);
        $cmd = strtolower(trim($request[0]));
        if ($cmd == 'push') {
            if (!isset($request[1]) or empty(rtrim($request[1]))) {
                $server->send($client_id, 'ERROR data field is required' . self::CRLF);
                return;
            }
            $item = rtrim($request[1]);
            $this->queue->push($item);
            $server->send($client_id, 'OK' . self::CRLF);
        } elseif ($cmd == 'pop') {
            $data = $this->queue->pop();
            if (empty($data)) {
                $server->send($client_id, 'ERROR queue empty' . self::CRLF);
                return;
            }
            $server->send($client_id, $data . self::CRLF);
        } elseif ($cmd == 'save') {
            if (method_exists($this->queue, 'save')) {
                $this->queue->save();
            }
            $server->send($client_id, 'OK' . self::CRLF);
        } else {
            $server->send($client_id, 'ERROR unsupported command' . self::CRLF);
        }
    }

    function onConnect($server, $client_id, $tid)
    {
        $this->app->log->info("login");
    }

    function onClose($server, $client_id, $tid)
    {
        $this->app->log->info("logout");
    }

    function onStart($server)
    {
        $this->app->log->info("server running");
    }

    function onShutdown($server)
    {
        $this->app->log->info("server shutdown");
    }
}
