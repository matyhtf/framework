<?php

namespace SPF\Rpc\Client\Middlewares;

use SPF\Rpc\Client;
use SPF\Rpc\Client\Request;
use SPF\Rpc\Protocol\ProtocolHeader;

class PacketHeader
{
    /**
     * @param \SPF\Rpc\Client\Request $request
     * @param \Closure $next
     * 
     * @return $next($request)
     */
    public static function handle(Request $request, \Closure $next)
    {
        // 对包体增加32字节header
        $reqPacket = static::encodePacket($request);

        $response = $next($request);

        // 对包体解析32字节header 
        return static::decodePacket($response);
    }

    protected static function encodePacket(Request $request)
    {
        $uid = 0;
        $errno = 0;
        return ProtocolHeader::encode(
            $request->buffer,
            strlen($request->buffer),
            $this->config['format'],
            $errno,
            $request->requestId(),
            $uid,
            Client::SDK_VERSION,
            $request->getConfig('sdkVersion')
        );
    }

    protected static function decodePacket($response)
    {
        return ProtocolHeader::decode($response);
    }
}
