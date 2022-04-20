<?php

use PHPUnit\Framework\TestCase;
use SPF\Client\WebSocket;

class WebSocketTest extends TestCase
{
    public function test400KPacket()
    {
        $client = new WebSocket('127.0.0.1', 9501, '/');
        $this->assertTrue($client->connect(2));
        $length = 400 * 1024;
        $sentData = base64_encode(random_bytes($length));
        $this->assertLessThan($client->send($sentData), strlen($sentData));
        $message = $client->recv();
        $expectRecvData = 'Server: ' . $sentData;
        $this->assertEquals($message, $expectRecvData);
    }
}
