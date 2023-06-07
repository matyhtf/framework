<?php

namespace SPF\Rpc\Protocol;

use SPF\Rpc\Formatter\FormatterFactory;
use Throwable;

/**
 * @property \swoole_server|\swoole_http_server|\swoole_websocket_server $server
 * @method \Symfony\Component\Console\Output\ConsoleOutput console()
 */
trait TcpProtocol
{
    /**
     * 创建tcp服务
     *
     * @param array $conn 配置信息
     */
    protected function createTcpServer($conn)
    {
        if (is_null($this->server)) {
            $this->server = new \swoole_server($conn['host'], $conn['port'], $conn['mode'], $conn['type']);
            $this->server->on('Connect', [$this, 'onConnect']);
            $this->server->on('Receive', [$this, 'onReceive']);
            $this->server->on('Close', [$this, 'onClose']);
            
            if (!empty($conn['settings'])) {
                $this->server->set($conn['settings']);
            }
        } else {
            $server = $this->server->addListener($conn['host'], $conn['port'], $conn['type']);
            $server->on('Connect', [$this, 'onConnect']);
            $server->on('Receive', [$this, 'onReceive']);
            $server->on('Close', [$this, 'onClose']);

            $settings = [
                'open_http_protocol' => false,
                'open_http2_protocol' => false,
                'open_websocket_protocol' => false,
            ];
            if (!empty($conn['settings'])) {
                $settings = array_merge($settings, $conn['settings']);
            }
            $server->set($settings);
        }

        $this->console()->writeln("<info>Listen on tcp://{$conn['host']}:{$conn['port']}</info>");
    }

    /**
     * TCP连接建立连接
     */
    public function onConnect(\swoole_server $server, int $fd, int $reactorId)
    {
        // do something
    }

    /**
     * 接收Tcp消息
     */
    public function onReceive(\swoole_server $server, int $fd, int $reactorId, string $data)
    {
        try {
            $header = [
                'formatter' => FormatterFactory::FMT_TARS,
                'request_id' => 0,
                'uid' => 0,
            ];

            try {
                $this->beforeOnReceive($server, $fd, $reactorId, $data);

                $clientInfo = [];
                $responseBuf = $this->handleRequest('tcp', $data, $clientInfo, $header);
            } catch (Throwable $e) {
                $responseBuf = $this->encodePacket('', $header, $e->getCode());

                $this->debugExceptionOutput($e);
            }

            $server->send($fd, $responseBuf);
        } catch (Throwable $e) {
            // TODO 系统错误，记录异常日志或者发送告警
            // $code = $e->getCode();
            // $msg = $e->getMessage();
            // $this->console()->writeln("<error>系统错误：[{$code}] {$msg}</error>");
            $this->debugExceptionOutput($e);
        }
    }

    protected function beforeOnReceive(\swoole_server $server, int $fd, int $reactorId, string $data)
    {
        // do something
    }

    /**
     * TCP连接断开
     */
    public function onClose(\swoole_server $server, int $fd, int $reactorId)
    {
        // do something
    }
}
