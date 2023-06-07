<?php

namespace SPF\Protocol;

use SPF;

abstract class WebSocket extends HttpServer
{
    const OPCODE_CONTINUATION_FRAME = 0x0;
    const OPCODE_TEXT_FRAME = 0x1;
    const OPCODE_BINARY_FRAME = 0x2;
    const OPCODE_CONNECTION_CLOSE = 0x8;
    const OPCODE_PING = 0x9;
    const OPCODE_PONG = 0xa;

    const CLOSE_NORMAL = 1000;
    const CLOSE_GOING_AWAY = 1001;
    const CLOSE_PROTOCOL_ERROR = 1002;
    const CLOSE_DATA_ERROR = 1003;
    const CLOSE_STATUS_ERROR = 1005;
    const CLOSE_ABNORMAL = 1006;
    const CLOSE_MESSAGE_ERROR = 1007;
    const CLOSE_POLICY_ERROR = 1008;
    const CLOSE_MESSAGE_TOO_BIG = 1009;
    const CLOSE_EXTENSION_MISSING = 1010;
    const CLOSE_SERVER_ERROR = 1011;
    const CLOSE_TLS = 1015;

    const WEBSOCKET_VERSION = 13;
    /**
     * GUID.
     *
     * @const string
     */
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    protected $frame_list = [];
    protected $buffer_list = [];
    public $connections = [];
    public $max_connect = 10000;
    public $max_frame_size = 2097152; //数据包最大长度，超过此长度会被认为是非法请求
    public $heart_time = 600; //600s life time

    public $keepalive = true;

    /**
     * Do the handshake.
     *
     * @param SPF\Request $request
     * @param SPF\Response $response
     * @return  bool
     * @throws   \Exception
     */
    public function doHandshake(SPF\Request $request, SPF\Response $response)
    {
        if (!isset($request->header['Sec-WebSocket-Key'])) {
            $this->log('Bad protocol implementation: it is not RFC6455.');
            return false;
        }
        $key = $request->header['Sec-WebSocket-Key'];
        if (0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $key) || 16 !== strlen(base64_decode($key))) {
            $this->log('Header Sec-WebSocket-Key: $key is illegal.');
            return false;
        }
        /**
         * @TODO
         *   ? Origin;
         *   ? Sec-WebSocket-Protocol;
         *   ? Sec-WebSocket-Extensions.
         */
        $response->setHttpStatus(101);
        $response->addHeaders(array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => base64_encode(sha1($key . static::GUID, true)),
            'Sec-WebSocket-Version' => self::WEBSOCKET_VERSION,
        ));
        return true;
    }

    public function onConnect($serv, $fd, $from_id)
    {
        $this->cleanBuffer($fd);
    }

    /**
     * clean all connection
     */
    public function cleanConnection()
    {
        $now = time();
        foreach ($this->connections as $client_id => $conn) {
            if ($conn['time'] < $now - $this->heart_time) {
                $this->log("connection[$client_id] timeout.", 'CLOSE');
                $this->close($client_id);
            }
        }
        $this->log('clean connections');
    }

    /**
     * 收到消息
     * @param $client_id
     * @param $message
     *
     * @return mixed
     */
    abstract public function onMessage($client_id, $message);

    /**
     * 客户端退出
     * @param $client_id
     * @return mixed
     */
    public function onExit($client_id)
    {
    }

    public function onClose($serv, $client_id, $from_id)
    {
        $this->onExit($client_id);
    }

    /**
     *
     * @param $client_id
     */
    public function onEnter($client_id)
    {
    }

    /**
     * Called on WebSocket connection established.
     *
     * @param $client_id
     * @param $request
     */
    public function onWsConnect($client_id, $request)
    {
        $this->log("WebSocket connection #$client_id is connected");
        $this->onEnter($client_id);
    }

    /**
     * Produce response for Http request.
     *
     * @param $request
     * @return SPF\Response
     */
    public function onHttpRequest(SPF\Request $request)
    {
        return parent::onRequest($request);
    }

    /**
     * Produce response for WebSocket request.
     * @param SPF\Request $request
     * @return SPF\Response
     * @throws \Exception
     */
    public function onWebSocketRequest(SPF\Request $request)
    {
        $response = $this->currentResponse = new SPF\Response();
        $this->doHandshake($request, $response);
        return $response;
    }

    /**
     * Request come
     * @param SPF\Request $request
     * @return SPF\Response
     * @throws \Exception
     */
    public function onRequest(SPF\Request $request)
    {
        return $request->isWebSocket() ? $this->onWebSocketRequest($request) : $this->onHttpRequest($request);
    }

    /**
     * Clean and fire onWsConnect().
     * @param SPF\Request $request
     * @param SPF\Response $response
     */
    public function afterResponse(SPF\Request $request, SPF\Response $response)
    {
        if ($request->isWebSocket()) {
            $conn = array('header' => $request->header, 'time' => time());
            $this->connections[$request->fd] = $conn;

            if (count($this->connections) > $this->max_connect) {
                $this->cleanConnection();
            }

            $this->onWsConnect($request->fd, $request);
        }
        parent::afterResponse($request, $response);
    }

    /**
     * Read a frame.
     *
     * @access  public
     * @throw   \Exception
     */
    public function onReceive($server, $fd, $tid, $data)
    {
        $this->log("Connection[{$fd}] received ".strlen($data)." bytes.");
        //未完成握手，先进入 Http 握手阶段
        if (!isset($this->connections[$fd])) {
            return parent::onReceive($server, $fd, $tid, $data);
        }

        //处理数据
        while (strlen($data) > 0 and isset($this->connections[$fd])) {
            //新的请求
            if (!isset($this->frame_list[$fd])) {
                $frame = $this->parseFrame($data);
                if ($frame === false) {
                    $this->log("Error Frame");
                    $this->close($fd);
                    break;
                }
                //数据完整
                if ($frame['completed']) {
                    $this->log("NewFrame finish. Opcode=" . $frame['opcode'] . "|Length={$frame['length']}");
                    $this->opcodeSwitch($fd, $frame);
                } //数据不完整加入到缓存中
                else {
                    $this->frame_list[$fd] = $frame;
                    $this->log("[total length] = ".$frame['length'].'. [current length] = '.strlen($frame['data']));
                    break;
                }
            } else {
                $frame = &$this->frame_list[$fd];
                $frame['data'] .= $data;

                $this->log("[total length] = ".$frame['length'].'. [current length] = '.strlen($frame['data']));

                //数据已完整，进行处理
                if (strlen($frame['data']) >= $frame['length']) {
                    $frame['completed'] = true;
                    $data = substr($frame['data'], $frame['length']);
                    $frame['data'] = substr($frame['data'], 0, $frame['length']);
                    $this->opcodeSwitch($fd, $frame);
                    unset($this->frame_list[$fd]);
                }
                //数据不足，跳出循环，继续等待数据
                else {
                    break;
                }
            }
        }
        return true;
    }

    /**
     * 解析数据帧
     * 返回false表示解析失败，需要关闭此连接
     * @param $buffer
     * @return array|bool
     */
    public function parseFrame(&$buffer)
    {
        $this->log("PaserFrame. BufferLen=".strlen($buffer));
        //websocket
        $ws = array();
        $ws['completed'] = false;

        $data_offset = 0;

        //fin:1 rsv1:1 rsv2:1 rsv3:1 opcode:4
        $handle = ord($buffer[$data_offset]);
        $ws['fin'] = ($handle >> 7) & 0x1;
        $ws['rsv1'] = ($handle >> 6) & 0x1;
        $ws['rsv2'] = ($handle >> 5) & 0x1;
        $ws['rsv3'] = ($handle >> 4) & 0x1;
        $ws['opcode'] = $handle & 0xf;
        $data_offset++;

        //mask:1 length:7
        $handle = ord($buffer[$data_offset]);
        $ws['mask'] = ($handle >> 7) & 0x1;
        //0-125
        $ws['length'] = $handle & 0x7f;
        $length =  &$ws['length'];
        $data_offset++;

        if ($length == 0x7e) {
            $handle = unpack('nl', substr($buffer, $data_offset, 2));
            $data_offset += 2;
            $length = $handle['l'];
        } elseif ($length > 0x7e) {
            $handle = unpack('Nl1/Nl2', substr($buffer, $data_offset, 8));
            $data_offset += 8;
            $length = ($handle['l1'] << 32) + $handle['l2'];
            if ($length > $this->max_frame_size) {
                $this->log('Message is too long.');
                return false;
            }
        }

        //mask-key: int32
        if (0x0 !== $ws['mask']) {
            $ws['mask'] = array_map('ord', str_split(substr($buffer, $data_offset, 4)));
            $data_offset += 4;
        }

        //去掉 websocket 头
        $buffer = substr($buffer, $data_offset);

        //数据长度为0的帧
        if (0 === $length) {
            $ws['completed'] = true;
            $ws['message'] = '';
            return $ws;
        }

        //完整的一个数据帧
        if (strlen($buffer) >= $length) {
            $ws['completed'] = true;
            $ws['data'] = substr($buffer, 0, $length);
            //截取数据
            $buffer = substr($buffer, $length);
            return $ws;
        }
        //需要继续等待数据
        else {
            $ws['completed'] = false;
            $ws['data'] = $buffer;
            $buffer = "";
            return $ws;
        }
    }

    protected function parseMessage($ws)
    {
        $data = $ws['data'];
        //没有mask
        if (0x0 !== $ws['mask']) {
            $maskC = 0;
            for ($j = 0, $_length = $ws['length']; $j < $_length; ++$j) {
                $data[$j] = chr(ord($data[$j]) ^ $ws['mask'][$maskC]);
                $maskC = ($maskC + 1) % 4;
            }
        }
        return $data;
    }

    /**
     * Write a frame.
     *
     * @access  public
     * @param string $message Message.
     * @param int $opcode Opcode.
     * @param bool $end
     * @return  int
     */
    public function newFrame($message, $opcode = self::OPCODE_TEXT_FRAME, $end = true)
    {
        $fin = true === $end ? 0x1 : 0x0;
        $rsv1 = 0x0;
        $rsv2 = 0x0;
        $rsv3 = 0x0;
        $length = strlen($message);
        $out = chr(($fin << 7) | ($rsv1 << 6) | ($rsv2 << 5) | ($rsv3 << 4) | $opcode);

        if (0xffff < $length) {
            $out .= chr(0x7f) . pack('NN', 0, $length);
        } elseif (0x7d < $length) {
            $out .= chr(0x7e) . pack('n', $length);
        } else {
            $out .= chr($length);
        }
        $out .= $message;
        return $out;
    }

    /**
     * Send a message.
     *
     * @access  public
     * @param int $client_id
     * @param string $message Message.
     * @param int $opcode Opcode.
     * @param bool $end Whether it is the last frame of the message.
     * @return  bool
     */
    public function send($client_id, $message, $opcode = self::OPCODE_TEXT_FRAME, $end = true)
    {
        if ((self::OPCODE_TEXT_FRAME === $opcode or self::OPCODE_CONTINUATION_FRAME === $opcode) and false === (bool)preg_match(
            '//u',
            $message
        )) {
            $this->log(
                'Message [%s] is not in UTF-8, cannot send it.',
                2,
                32 > strlen($message) ? substr($message, 0, 32) . ' ' : $message
            );
            return false;
        } else {
            $out = $this->newFrame($message, $opcode, $end);
            return $this->server->send($client_id, $out);
        }
    }

    /**
     * opcode switch
     * @param $client_id
     * @param $frame
     */
    public function opcodeSwitch($client_id, &$frame)
    {
        $this->log("[$client_id] opcode={$frame['opcode']}, fin={$frame['fin']}, length={$frame['length']}\n".str_repeat('-', 80));

        if (!empty($frame['data'])) {
            $frame['message'] = $this->parseMessage($frame);
            unset($frame['data']);
        }

        switch ($frame['opcode']) {
            //data frame
            case self::OPCODE_BINARY_FRAME:
            case self::OPCODE_TEXT_FRAME:
            case self::OPCODE_CONTINUATION_FRAME:
                /**
                 * 等待数据合并
                 */
                if (isset($this->buffer_list[$client_id])) {
                    $this->buffer_list[$client_id]['message'] .= $frame['message'];
                    $this->log('merge data, [total length]=' . strlen($this->buffer_list[$client_id]['message']) .
                        ', [chunk length]=' . strlen($frame['message']));
                }

                if (0x1 === $frame['fin']) {
                    if (isset($this->buffer_list[$client_id])) {
                        $frame = $this->buffer_list[$client_id];
                        $frame['length'] = strlen($frame['message']);
                        unset($this->buffer_list[$client_id]);
                    }
                    $this->onMessage($client_id, $frame);
                } else {
                    if (!isset($this->buffer_list[$client_id])) {
                        $this->buffer_list[$client_id] = $frame;
                    }
                }
                break;

            //heartbeat
            case self::OPCODE_PING:
                $message = &$frame['message'];
                if (0x0 === $frame['fin'] or 0x7d < $frame['length']) {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR, "ping error");
                    break;
                }
                $this->connections[$client_id]['time'] = time();
                $this->send($client_id, $message, self::OPCODE_PONG, true);
                break;

            case self::OPCODE_PONG:
                if (0 === $frame['fin']) {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR, "pong? server cannot pong.");
                }
                break;

            //close the connection
            case self::OPCODE_CONNECTION_CLOSE:
                $length = &$frame['length'];
                if (1 === $length or 0x7d < $length) {
                    $this->close($client_id, self::CLOSE_PROTOCOL_ERROR, "client active close");
                    break;
                }

                if ($length > 0) {
                    $message = $frame['message'];
                    $_code = unpack('nc', substr($message, 0, 2));
                    $code = $_code['c'];

                    if ($length > 2) {
                        $reason = substr($message, 2);
                        if (false === (bool)preg_match('//u', $reason)) {
                            $this->close($client_id, self::CLOSE_MESSAGE_ERROR);
                            break;
                        }
                    }

                    if (1000 > $code || (1004 <= $code && $code <= 1006) || (1012 <= $code && $code <= 1016) || 5000 <= $code) {
                        $this->close($client_id, self::CLOSE_PROTOCOL_ERROR);
                        break;
                    } else {
                        $this->close($client_id, self::CLOSE_PROTOCOL_ERROR, "client active close, code={$code}");
                        break;
                    }
                }
                break;
            default:
                $this->close($client_id, self::CLOSE_PROTOCOL_ERROR, "unkown websocket opcode[{$frame['opcode']}]");
                break;
        }
    }

    /**
     * Close a connection.
     *
     * @access  public
     * @param int $fd
     * @param int $code
     * @param string $reason Reason.
     *
     * @return  bool
     */
    public function close($fd, $code = self::CLOSE_NORMAL, $reason = '')
    {
        $this->send($fd, pack('n', $code) . $reason, self::OPCODE_CONNECTION_CLOSE);
        $this->log("server close connection[$fd]. reason: $reason, close_code = $code");
        return $this->server->close($fd);
    }

    /**
     * 清理连接缓存区
     * @param $fd
     */
    public function cleanBuffer($fd)
    {
        unset($this->frame_list[$fd], $this->connections[$fd]);
        parent::cleanBuffer($fd);
    }
}
