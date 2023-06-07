<?php

namespace SPF\Rpc\Protocol;

use SPF\Rpc\Formatter\FormatterFactory;
use Throwable;

/**
 * @property \swoole_server|\swoole_http_server|\swoole_websocket_server $server
 * @method \Symfony\Component\Console\Output\ConsoleOutput console()
 */
trait HttpProtocol
{
    /**
     * 创建http服务
     *
     * @param array $conn 配置信息
     */
    protected function createHttpServer($conn)
    {
        if (is_null($this->server)) {
            $this->server = new \swoole_http_server($conn['host'], $conn['port']);
            $this->server->on('Request', [$this, 'onRequest']);
            
            if (!empty($conn['settings'])) {
                $this->server->set($conn['settings']);
            }
        } else {
            $server = $this->server->addListener($conn['host'], $conn['port']);
            $server->on('Request', [$this, 'onRequest']);

            $settings = [
                'open_http_protocol' => true,
                'open_http2_protocol' => false,
                'open_websocket_protocol' => false,
            ];
            if (!empty($conn['settings'])) {
                $settings = array_merge($settings, $conn['settings']);
            }
            $server->set($settings);
        }

        $this->console()->writeln("<info>Listen on http://{$conn['host']}:{$conn['port']}</info>");
    }

    public function onRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        try {
            $header = [
                'formatter' => FormatterFactory::FMT_TARS,
                'request_id' => 0,
                'uid' => 0,
            ];

            try {
                $this->beforeOnRequest($request, $response);

                $clientInfo = [];
                $data = $request->rawContent();
                $responseBuf = $this->handleRequest('http', $data, $clientInfo, $header);
            } catch (Throwable $e) {
                $responseBuf = $this->encodePacket('', $header, $e->getCode());

                $this->debugExceptionOutput($e);
            }

            $response->end($responseBuf);
        } catch (Throwable $e) {
            // TODO 系统错误，记录异常日志或者发送告警
            // $code = $e->getCode();
            // $msg = $e->getMessage();
            // $this->console()->writeln("<error>系统错误：[{$code}] {$msg}</error>");
            $this->debugExceptionOutput($e);
        }
    }

    protected function beforeOnRequest(\swoole_http_request $request, \swoole_http_response $response)
    {
        // TODO
    }
}
