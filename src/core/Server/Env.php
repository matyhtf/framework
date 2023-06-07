<?php
namespace SPF\Server;

use SPF;

class Env
{
    public static function getEnv(): ?array
    {
        if (!SPF\Network\Server::$useSwooleHttpServer) {
            return SPF\Protocol\RPCServer::$clientEnv;
        } else {
            return SPF\Http\ExtServer::$clientEnv;
        }
    }
}
