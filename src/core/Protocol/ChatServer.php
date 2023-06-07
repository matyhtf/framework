<?php

namespace SPF\Protocol;

use SPF\Filter;

class ChatServer extends Base
{
    public $default_port = 8080;
    //保存用户名称
    public $chat_unames;
    //保存client的Socket_id
    public $chat_client;
    //返回数据格式为JSON
    public $dataType = 'json';

    public function log($msg)
    {
        echo $msg, PHP_EOL;
    }

    public function sendMsg($msg, $from, $to, $client_id)
    {
        $data['from'] = $from;
        $data['to'] = $to;
        $data['msg'] = $msg;
        $data['type'] = 'msg';
        $send = json_encode($data);
        if ($to == 0) {
            $this->server->sendAll($client_id, $send);
        } else {
            $this->server->send($this->chat_client[$to], $send);
        }
    }

    public function sysNotice($msg, $client_id)
    {
        $data['msg'] = $msg;
        $data['type'] = 'sys';
        $send = json_encode($data);
        $this->server->sendAll($client_id, $send);
    }

    public function onReceive($server, $client_id, $tid, $data)
    {
        $msg = explode(' ', $data, 3);
        $this->log($client_id . $data);
        if ($msg[0] == '/setname') {
            $uid = (int)$msg[1];
            $uname = $msg[2];
            if (isset($this->chat_unames[$uid])) {
                $this->server->send($client_id, 'user exists');
            } else {
                $this->chat_client[$uid] = $client_id;
                $this->chat_unames[$uid] = $uname;
                $this->server->send($client_id, 'setname success');
                $this->sysNotice('login:' . $uid . ':' . $uname, $client_id);
            }
        } elseif ($msg[0] == '/sendto') {
            $to = (int)$msg[1];
            $from = array_search($client_id, $this->chat_client);
            $content = Filter::escape($msg[2]);
            if (isset($this->chat_client[$to])) {
                $this->sendMsg($content, $from, $to, $client_id);
            } else {
                $this->server->send($client_id, 'user not exists');
            }
        } elseif ($msg[0] == '/sendall') {
            $from = array_search($client_id, $this->chat_client);
            $content = Filter::escape($msg[1]);
            $this->sendMsg($content, $from, 0, $client_id);
        } elseif ($msg[0] == '/getusers') {
            $this->server->send($client_id, 'users:' . json_encode($this->chat_unames));
        }
    }

    public function onStart($server)
    {
    }

    public function onShutdown($server)
    {
    }

    public function onClose($server, $client_id, $tid)
    {
        $uid = array_search($client_id, $this->chat_client);
        unset($this->chat_client[$uid], $this->chat_unames[$uid]);
        $this->log('user logout!');
        $this->sysNotice('logout:' . $uid, $client_id);
    }

    public function onConnect($server, $client_id, $tid)
    {
    }
}
