<?php
namespace SPF\Client;

use SPF;

class WebSocket
{
    const VERSION = '0.1.4';
    const TOKEN_LENGHT = 16;

    const TYPE_ID_WELCOME = 0;
    const TYPE_ID_PREFIX = 1;
    const TYPE_ID_CALL = 2;
    const TYPE_ID_CALLRESULT = 3;
    const TYPE_ID_ERROR = 4;
    const TYPE_ID_SUBSCRIBE = 5;
    const TYPE_ID_UNSUBSCRIBE = 6;
    const TYPE_ID_PUBLISH = 7;
    const TYPE_ID_EVENT = 8;

    protected $key;
    protected $host;
    protected $port;
    protected $path;

    /**
     * @var TCP
     */
    protected $socket;
    protected $buffer = '';

    /**
     * @var bool
     */
    protected $connected = false;
    protected $handshake = false;
    protected $ssl = false;
    protected $ssl_key_file;
    protected $ssl_cert_file;

    protected $haveSwooleEncoder = false;

    protected $header;
    
    const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    const UserAgent = 'SwooleWebsocketClient';

    /**
     * @param string $host
     * @param int $port
     * @param string $path
     * @throws SPF\Http\WebSocketException
     */
    function __construct($host, $port = 80, $path = '/')
    {
        if (empty($host))
        {
            throw new SPF\Http\WebSocketException("require websocket server host.");
        }
        $this->haveSwooleEncoder = method_exists('swoole_websocket_server', 'pack');
        $this->host = $host;
        $this->port = $port;
        $this->path = $path;
        $this->key = $this->generateToken(self::TOKEN_LENGHT);
        $this->parser = new SPF\Http\WebSocketParser();
    }

    /**
     * @param string $keyFile
     * @param string $certFile
     * @throws SPF\Http\WebSocketException
     */
    function enableCrypto($keyFile = '', $certFile = '')
    {
        if (!extension_loaded('swoole'))
        {
            throw new SPF\Http\WebSocketException("require swoole extension.");
        }
        $this->ssl = true;
        $this->ssl_key_file = $keyFile;
        $this->ssl_cert_file = $certFile;
    }

    /**
     * Disconnect on destruct
     */
    function __destruct()
    {
        if ($this->connected)
        {
            $this->disconnect();
        }
    }

    /**
     * Connect client to server
     * @param float $timeout
     * @return bool
     */
    public function connect($timeout = 0.5)
    {
        if (extension_loaded('swoole'))
        {
            $type = preg_match('/^[0-9\.]+$/', $this->host) ? SWOOLE_SOCK_TCP : SWOOLE_SOCK_UNIX_STREAM;
            if ($this->ssl)
            {
                $type |= SWOOLE_SSL;
            }
            $this->socket = new \swoole_client($type);
            if ($this->ssl_key_file)
            {
                $this->socket->set(array(
                    'ssl_key_file' => $this->ssl_key_file,
                    'ssl_cert_file' => $this->ssl_cert_file
                ));
            }
        }
        else
        {
            $this->socket = new TCP;
        }
        //建立连接
        if (!$this->socket->connect($this->host, $this->port, $timeout))
        {
            return false;
        }
        $this->connected = true;
        //WebSocket握手
        if ($this->socket->send($this->createHeader()) === false)
        {
            return false;
        }
        $headerBuffer = '';
        while(true)
        {
            $_tmp = $this->socket->recv();
            if ($_tmp)
            {
                $headerBuffer .= $_tmp;
                $offset = stripos($headerBuffer,"\r\n\r\n");
                if ($offset === false) {
                    continue;
                }
                $header = substr($headerBuffer, 0, $offset+4);
                $this->buffer = substr($headerBuffer, $offset+4);
            }
            else
            {
                return false;
            }
            return $this->doHandShake($header);
        }
        return false;
    }

    /**
     * 握手
     * @param $headerBuffer
     * @return bool
     */
    function doHandShake($headerBuffer)
    {
        $header = SPF\Http\Parser::parseHeader($headerBuffer);
        $_header_key_low_case = array_change_key_case($header, CASE_LOWER);
        if (!isset($_header_key_low_case['sec-websocket-accept']))
        {
            $this->disconnect();
            return false;
        }
        if ($_header_key_low_case['sec-websocket-accept'] != base64_encode(pack('H*', sha1($this->key . self::GUID))))
        {
            $this->disconnect();
            return false;
        }
        $this->handshake = true;
        $this->header = $header;
        return true;
    }

    /**
     * Disconnect from server
     */
    public function disconnect()
    {
        $this->connected = false;
        $this->socket->close();
    }

    /**
     * 接收数据
     * @return bool | SPF\Http\WebSocketFrame
     * @throws SPF\Http\WebSocketException
     */
    function recv()
    {
        if (!$this->handshake)
        {
            trigger_error("not complete handshake.");
            return false;
        }
        if ($this->buffer and  $offset = stripos($this->buffer,"\r\n\r\n") !== false)
        {
            $packet = substr($this->buffer, 0, $offset + 4);
            $next = substr($this->buffer, $offset + 4);
            if ($next) {
                $this->buffer = $next;
            }
            $this->parser->push($packet);
            $frame = $this->parser->pop();
            if ($frame)
            {
                return $frame->data;
            }
        } else {
            while (true)
            {
                $data = $this->socket->recv();
                if ($this->buffer) {
                    $data = $this->buffer.$data;
                    $this->buffer = '';
                }
                if (!$data)
                {
                    return false;
                }
                $this->parser->push($data);
                $frame = $this->parser->pop();
                if ($frame)
                {
                    return $frame->data;
                }
            }
        }
        return false;
    }

    /**
     * send string data
     * @param $data
     * @param string $type
     * @param bool $masked
     * @throws \Exception
     * @return bool
     */
    public function send($data, $type = 'text', $masked = true)
    {
        if (empty($data))
        {
            throw new \Exception("data is empty");
        }
        if (!$this->handshake)
        {
            trigger_error("not complete handshake.");
            return false;
        }
        if ($this->haveSwooleEncoder)
        {
            switch($type)
            {
                case 'text':
                    $_type = WEBSOCKET_OPCODE_TEXT;
                    break;
                case 'binary':
                case 'bin':
                    $_type = WEBSOCKET_OPCODE_BINARY;
                    break;
                default:
                    return false;
            }
            $_send = \swoole_websocket_server::pack($data, $_type);
        }
        else
        {
            $_send =  $this->hybi10Encode($data, $type, $masked);
        }
        return $this->socket->send($_send);
    }

    /**
     * send json object
     * @param $data
     * @param bool $masked
     * @return bool
     */
    function sendJson($data, $masked = true)
    {
        return $this->send(json_encode($data), 'text', $masked);
    }

    /**
     * Create header for websocket client
     * @return string
     */
    final protected function createHeader()
    {
        $host = $this->host;
        if ($host === '127.0.0.1' || $host === '0.0.0.0')
        {
            $host = 'localhost';
        }

        return "GET {$this->path} HTTP/1.1" . "\r\n" .
        "Origin: null" . "\r\n" .
        "Host: {$host}:{$this->port}" . "\r\n" .
        "Sec-WebSocket-Key: {$this->key}" . "\r\n" .
        "User-Agent: ".self::UserAgent."/" . self::VERSION . "\r\n" .
        "Upgrade: Websocket" . "\r\n" .
        "Connection: Upgrade" . "\r\n" .
        "Sec-WebSocket-Protocol: wamp" . "\r\n" .
        "Sec-WebSocket-Version: 13" . "\r\n" . "\r\n";
    }

    /**
     * Parse raw incoming data
     *
     * @param $header
     * @return array
     */
    final protected function parseIncomingRaw($header)
    {
        $retval = array();
        $content = "";
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($matches) {
                    return strtoupper($matches[0]);
                }, strtolower(trim($match[1])));
                if (isset($retval[$match[1]])) {
                    $retval[$match[1]] = array($retval[$match[1]], $match[2]);
                } else {
                    $retval[$match[1]] = trim($match[2]);
                }
            } else if (preg_match('!HTTP/1\.\d (\d)* .!', $field)) {
                $retval["status"] = $field;
            } else {
                $content .= $field . "\r\n";
            }
        }
        $retval['content'] = $content;

        return $retval;
    }

    /**
     * Generate token
     *
     * @param int $length
     * @return string
     */
    private function generateToken($length)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!"§$%&/()=[]{}';

        $useChars = array();
        // select some random chars:
        for ($i = 0; $i < $length; $i++) {
            $useChars[] = $characters[mt_rand(0, strlen($characters) - 1)];
        }
        // Add numbers
        array_push($useChars, rand(0, 9), rand(0, 9), rand(0, 9));
        shuffle($useChars);
        $randomString = trim(implode('', $useChars));
        $randomString = substr($randomString, 0, self::TOKEN_LENGHT);

        return base64_encode($randomString);
    }

    /**
     * Generate token
     *
     * @param int $length
     * @return string
     */
    public function generateAlphaNumToken($length)
    {
        $characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');

        srand((float)microtime() * 1000000);

        $token = '';

        do
        {
            shuffle($characters);
            $token .= $characters[mt_rand(0, (count($characters) - 1))];
        } while (strlen($token) < $length);

        return $token;
    }

    /**
     * @param $payload
     * @param string $type
     * @param bool $masked
     * @return bool|string
     */
    private function hybi10Encode($payload, $type = 'text', $masked = true)
    {
        $frameHead = array();
        $payloadLength = strlen($payload);

        switch ($type)
        {
            //文本内容
            case 'text':
                // first byte indicates FIN, Text-Frame (10000001):
                $frameHead[0] = 129;
                break;

            //二进制内容
            case 'binary':
            case 'bin':
                // first byte indicates FIN, Text-Frame (10000010):
                $frameHead[0] = 130;
                break;

            case 'close':
                // first byte indicates FIN, Close Frame(10001000):
                $frameHead[0] = 136;
                break;

            case 'ping':
                // first byte indicates FIN, Ping frame (10001001):
                $frameHead[0] = 137;
                break;

            case 'pong':
                // first byte indicates FIN, Pong frame (10001010):
                $frameHead[0] = 138;
                break;
        }

        // set mask and payload length (using 1, 3 or 9 bytes)
        if ($payloadLength > 65535)
        {
            $payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 255 : 127;
            for ($i = 0; $i < 8; $i++)
            {
                $frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
            }
            // most significant bit MUST be 0 (close connection if frame too big)
            if ($frameHead[2] > 127)
            {
                $this->socket->close();
                return false;
            }
        }
        elseif ($payloadLength > 125)
        {
            $payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
            $frameHead[1] = ($masked === true) ? 254 : 126;
            $frameHead[2] = bindec($payloadLengthBin[0]);
            $frameHead[3] = bindec($payloadLengthBin[1]);
        }
        else
        {
            $frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
        }

        // convert frame-head to string:
        foreach (array_keys($frameHead) as $i)
        {
            $frameHead[$i] = chr($frameHead[$i]);
        }

        // generate a random mask:
        $mask = array();
        if ($masked === true)
        {
            for ($i = 0; $i < 4; $i++)
            {
                $mask[$i] = chr(rand(0, 255));
            }
            $frameHead = array_merge($frameHead, $mask);
        }
        $frame = implode('', $frameHead);
        // append payload to frame:
        for ($i = 0; $i < $payloadLength; $i++)
        {
            $frame .= $masked ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
        }
        return $frame;
    }

    /**
     * @param $data
     * @return string
     * @throws WebSocketException
     */
    private function hybi10Decode($data)
    {
        if (empty($data))
        {
            throw new WebSocketException("data is empty");
        }

        $bytes = $data;
        $secondByte = sprintf('%08b', ord($bytes[1]));
        $masked = ($secondByte[0] == '1') ? true : false;
        $dataLength = ($masked === true) ? ord($bytes[1]) & 127 : ord($bytes[1]);
        //服务器不会设置mask
        if ($dataLength === 126)
        {
            $decodedData = substr($bytes, 4);
        }
        elseif ($dataLength === 127)
        {
            $decodedData = substr($bytes, 10);
        }
        else
        {
            $decodedData = substr($bytes, 2);
        }
        exit("len=".$dataLength."\n");
        return $decodedData;
    }
}

