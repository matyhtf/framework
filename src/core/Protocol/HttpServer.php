<?php
namespace SPF\Protocol;

use SPF;

/**
 * HTTP Server
 * @author Tianfeng.Han
 * @link http://www.swoole.com/
 * @package SPF
 * @subpackage net.protocol
 */
class HttpServer extends SPF\Protocol\WebServer implements SPF\IFace\Protocol
{
    protected $swoole_server;
    protected $buffer_header = array();
    protected $buffer_maxlen = 65535; //最大POST尺寸，超过将写文件

    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';

    const HTTP_EOF = "\r\n\r\n";
    const HTTP_HEAD_MAXLEN = 8192; //http头最大长度不得超过2k

    const ST_FINISH = 1; //完成，进入处理流程
    const ST_WAIT   = 2; //等待数据
    const ST_ERROR  = 3; //错误，丢弃此包

    public function __construct($config = array())
    {
        parent::__construct($config);
        $mimes = require(dirname(dirname(__DIR__)) . '/data/mimes.php');
        $this->mime_types = array_flip($mimes);
        $this->config = $config;
        $this->parser = new SPF\Http\Parser;
    }

    public function onStart($serv, $worker_id = 0)
    {
        if (isset($this->config['server']['user'])) {
            SPF\Console::changeUser($this->config['server']['user']);
        }

        SPF\Error::$echo_html = true;
        $this->swoole_server = $serv;
        SPF\App::$app->server = $this;
        $this->log(self::SOFTWARE . "[#{$worker_id}]. running on {$this->server->host}:{$this->server->port}");
        set_error_handler(array($this, 'onErrorHandle'), E_USER_ERROR);
        register_shutdown_function(array($this, 'onErrorShutDown'));
    }

    /**
     * @return \swoole_server
     */
    public function getSwooleServer()
    {
        return $this->swoole_server;
    }

    public function onShutdown($serv)
    {
        $this->log(self::SOFTWARE . " shutdown");
    }

    public function onConnect($serv, $client_id, $from_id)
    {
        $this->log("Event: client[#$client_id@$from_id] connect");
    }


    public function onClose($serv, $client_id, $from_id)
    {
        $this->log("Event: client[#$client_id@$from_id] close");
        $this->cleanBuffer($client_id);
    }

    public function cleanBuffer($fd)
    {
        unset($this->requests[$fd], $this->buffer_header[$fd]);
    }

    /**
     * @param $client_id
     * @param $http_data
     * @return bool|SPF\Request
     */
    public function checkHeader($client_id, $http_data)
    {
        //新的连接
        if (!isset($this->requests[$client_id])) {
            if (!empty($this->buffer_header[$client_id])) {
                $http_data = $this->buffer_header[$client_id].$http_data;
            }
            //HTTP结束符
            $ret = strpos($http_data, self::HTTP_EOF);
            //没有找到EOF，继续等待数据
            if ($ret === false) {
                return false;
            } else {
                $this->buffer_header[$client_id] = '';
                $request = new SPF\Request;
                //GET没有body
                list($header, $request->body) = explode(self::HTTP_EOF, $http_data, 2);
                $request->header = $this->parser->parseHeader($header);
                //使用head[0]保存额外的信息
                $request->meta = $request->header[0];
                unset($request->header[0]);
                //保存请求
                $this->requests[$client_id] = $request;
                //解析失败
                if ($request->header == false) {
                    $this->log("parseHeader failed. header=".$header);
                    return false;
                }
            }
        }
        //POST请求需要合并数据
        else {
            $request = $this->requests[$client_id];
            $request->body .= $http_data;
        }
        return $request;
    }

    /**
     * @param SPF\Request $request
     * @return int
     */
    public function checkPost(SPF\Request $request)
    {
        if (isset($request->header['Content-Length'])) {
            //超过最大尺寸
            if (intval($request->header['Content-Length']) > $this->config['server']['post_maxsize']) {
                $this->log("checkPost failed. post_data is too long.");
                return self::ST_ERROR;
            }
            //不完整，继续等待数据
            if (intval($request->header['Content-Length']) > strlen($request->body)) {
                return self::ST_WAIT;
            }
            //长度正确
            else {
                return self::ST_FINISH;
            }
        }
        $this->log("checkPost fail. Not have Content-Length.");
        //POST请求没有Content-Length，丢弃此请求
        return self::ST_ERROR;
    }

    public function checkData($client_id, $http_data)
    {
        if (isset($this->buffer_header[$client_id])) {
            $http_data = $this->buffer_header[$client_id].$http_data;
        }
        //检测头
        $request = $this->checkHeader($client_id, $http_data);
        //错误的http头
        if ($request === false) {
            $this->buffer_header[$client_id] = $http_data;
            //超过最大HTTP头限制了
            if (strlen($http_data) > self::HTTP_HEAD_MAXLEN) {
                $this->log("http header is too long.");
                return self::ST_ERROR;
            } else {
                $this->log("wait request data. fd={$client_id}");
                return self::ST_WAIT;
            }
        }
        //POST请求需要检测body是否完整
        if ($request->meta['method'] == 'POST') {
            return $this->checkPost($request);
        }
        //GET请求直接进入处理流程
        else {
            return self::ST_FINISH;
        }
    }

    /**
     * 接收到数据
     * @param $serv \swoole_server
     * @param $client_id
     * @param $tid
     * @param $data
     * @return null
     */
    public function onReceive($serv, $client_id, $tid, $data)
    {
        //检测request data完整性
        $ret = $this->checkData($client_id, $data);
        switch ($ret) {
            //错误的请求
            case self::ST_ERROR:
                $this->server->close($client_id);
                return;
            //请求不完整，继续等待
            case self::ST_WAIT:
                return;
            default:
                break;
        }
        //完整的请求
        //开始处理

        /**
         * @var $request SPF\Request
         */
        $request = $this->requests[$client_id];

        $request->fd = $client_id;
        $request->time = time();

        /**
         * Socket连接信息
         */
        $info = $serv->connection_info($client_id);
        $request->server['SWOOLE_CONNECTION_INFO'] = $info;
        $request->remote_ip = $info['remote_ip'];
        $request->remote_port = $info['remote_port'];
        /**
         * Server变量
         */
        $request->server['REQUEST_URI'] = $request->meta['uri'];
        $request->server['REMOTE_ADDR'] = $request->remote_ip;
        $request->server['REMOTE_PORT'] = $request->remote_port;
        $request->server['REQUEST_METHOD'] = $request->meta['method'];
        $request->server['REQUEST_TIME'] = $request->time;
        $request->server['SERVER_PROTOCOL'] = $request->meta['protocol'];
        if (!empty($request->meta['query'])) {
            $_SERVER['QUERY_STRING'] = $request->meta['query'];
        }
        $request->setGlobal();
        $this->parseRequest($request);
        $this->currentRequest = $request;
        //处理请求，产生response对象
        $response = $this->onRequest($request);
        if ($response and $response instanceof SPF\Response) {
            //发送response
            $this->response($request, $response);
        }
    }

    public function afterResponse(SPF\Request $request, SPF\Response $response)
    {
        if (!$this->keepalive or $response->head['Connection'] == 'close') {
            $this->server->close($request->fd);
        }
        $request->unsetGlobal();
        //清空request缓存区
        unset($this->requests[$request->fd]);
        unset($request);
        unset($response);
    }

    /**
     * 解析请求
     * @param $request SPF\Request
     * @return null
     */
    public function parseRequest($request)
    {
        $url_info = parse_url($request->meta['uri']);
        $request->meta['path'] = $url_info['path'];
        if (isset($url_info['fragment'])) {
            $request->meta['fragment'] = $url_info['fragment'];
        }
        if (isset($url_info['query'])) {
            parse_str($url_info['query'], $request->get);
        }
        //POST请求,有http body
        if ($request->meta['method'] === 'POST') {
            $this->parser->parseBody($request);
        }
        //解析Cookies
        if (!empty($request->header['Cookie'])) {
            $this->parser->parseCookie($request);
        }
    }

    /**
     * 发送响应
     * @param $request SPF\Request
     * @param $response SPF\Response
     * @return bool
     */
    public function response(SPF\Request $request, SPF\Response $response)
    {
        if (!isset($response->head['Date'])) {
            $response->head['Date'] = gmdate("D, d M Y H:i:s T");
        }
        if (!isset($response->head['Connection'])) {
            //keepalive
            if ($this->keepalive and (isset($request->header['Connection']) and strtolower($request->header['Connection']) == 'keep-alive')) {
                $response->head['KeepAlive'] = 'on';
                $response->head['Connection'] = 'keep-alive';
            } else {
                $response->head['KeepAlive'] = 'off';
                $response->head['Connection'] = 'close';
            }
        }
        //过期命中
        if ($this->expire and $response->http_status == 304) {
            $out = $response->getHeader();
            return $this->server->send($request->fd, $out);
        }
        //压缩
        if ($this->gzip) {
            if (!empty($request->header['Accept-Encoding'])) {
                //gzip
                if (strpos($request->header['Accept-Encoding'], 'gzip') !== false) {
                    $response->head['Content-Encoding'] = 'gzip';
                    $response->body = gzencode($response->body, $this->config['server']['gzip_level']);
                }
                //deflate
                elseif (strpos($request->header['Accept-Encoding'], 'deflate') !== false) {
                    $response->head['Content-Encoding'] = 'deflate';
                    $response->body = gzdeflate($response->body, $this->config['server']['gzip_level']);
                } else {
                    $this->log("Unsupported compression type : {$request->header['Accept-Encoding']}.");
                }
            }
        }

        $out = $response->getHeader().$response->body;
        $ret = $this->server->send($request->fd, $out);
        $this->afterResponse($request, $response);
        return $ret;
    }

    /**
     * 发生了http错误
     * @param                 $code
     * @param SPF\Response $response
     * @param string          $content
     */
    public function httpError($code, SPF\Response $response, $content = '')
    {
        $response->setHttpStatus($code);
        $response->head['Content-Type'] = 'text/html';
        $response->body = SPF\Error::info(
            SPF\Response::$HTTP_HEADERS[$code],
            "<p>$content</p><hr><address>" . self::SOFTWARE . " at {$this->server->host}" .
            " Port {$this->server->port}</address>"
        );
    }

    /**
     * 捕获register_shutdown_function错误
     */
    public function onErrorShutDown()
    {
        $error = error_get_last();
        if (!isset($error['type'])) {
            return;
        }
        switch ($error['type']) {
            case E_ERROR:
            case E_PARSE:
            case E_USER_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                break;
            default:
                return;
        }
        $this->errorResponse($error);
    }

    /**
     * 捕获set_error_handle错误
     */
    public function onErrorHandle($errno, $errstr, $errfile, $errline)
    {
        $error = array(
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        );
        $this->errorResponse($error);
    }

    /**
     * 错误显示
     * @param $error
     */
    private function errorResponse($error)
    {
        $errorMsg = "{$error['message']} ({$error['file']}:{$error['line']})";
        $message = SPF\Error::info(self::SOFTWARE." Application Error", $errorMsg);
        if (empty($this->currentResponse)) {
            $this->currentResponse = new SPF\Response();
        }
        $this->currentResponse->setHttpStatus(500);
        $this->currentResponse->body = $message;
        $this->currentResponse->body = (defined('DEBUG') && DEBUG == 'on') ? $message : '';
        $this->response($this->currentRequest, $this->currentResponse);
    }

    /**
     * 处理请求
     * @param $request
     * @return SPF\Response
     */
    public function onRequest(SPF\Request $request)
    {
        $response = new SPF\Response;
        $this->currentResponse = $response;
        SPF\App::getInstance()->request = $request;
        SPF\App::getInstance()->response = $response;

        //请求路径
        if ($request->meta['path'][strlen($request->meta['path']) - 1] == '/') {
            $request->meta['path'] .= $this->config['request']['default_page'];
        }

        if ($this->doStaticRequest($request, $response)) {
            //pass
        }
        /* 动态脚本 */
        elseif (isset($this->dynamic_ext[$request->ext_name]) or empty($ext_name)) {
            $this->processDynamic($request, $response);
        } else {
            $this->httpError(404, $response, "Http Not Found({($request->meta['path']})");
        }
        return $response;
    }

    /**
     * 过滤请求，阻止静止访问的目录，处理静态文件
     * @param SPF\Request $request
     * @param SPF\Response $response
     * @return bool
     */
    public function doStaticRequest(SPF\Request $request, SPF\Response $response)
    {
        $path = explode('/', trim($request->meta['path'], '/'));
        //扩展名
        $request->ext_name = $ext_name = SPF\Upload::getFileExt($request->meta['path']);
        /* 检测是否拒绝访问 */
        if (isset($this->deny_dir[$path[0]])) {
            $this->httpError(403, $response, "服务器拒绝了您的访问({$request->meta['path']})");
            return true;
        }
        /* 是否静态目录 */
        elseif (isset($this->static_dir[$path[0]]) or isset($this->static_ext[$ext_name])) {
            return $this->processStatic($request, $response);
        }
        return false;
    }

    /**
     * 处理静态请求
     * @param SPF\Request $request
     * @param SPF\Response $response
     * @return bool
     */
    public function processStatic(SPF\Request $request, SPF\Response $response)
    {
        $path = $this->document_root . '/' . $request->meta['path'];
        if (is_file($path)) {
            $read_file = true;
            if ($this->expire) {
                $expire = intval($this->config['server']['expire_time']);
                $fstat = stat($path);
                //过期控制信息
                if (isset($request->header['If-Modified-Since'])) {
                    $lastModifiedSince = strtotime($request->header['If-Modified-Since']);
                    if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince) {
                        //不需要读文件了
                        $read_file = false;
                        $response->setHttpStatus(304);
                    }
                } else {
                    $response->head['Cache-Control'] = "max-age={$expire}";
                    $response->head['Pragma'] = "max-age={$expire}";
                    $response->head['Last-Modified'] = date(self::DATE_FORMAT_HTTP, $fstat['mtime']);
                    $response->head['Expires'] = "max-age={$expire}";
                }
            }
            $ext_name = SPF\Upload::getFileExt($request->meta['path']);
            if ($read_file) {
                $response->head['Content-Type'] = $this->mime_types[$ext_name];
                $response->body = file_get_contents($path);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * 处理动态请求
     * @param SPF\Request $request
     * @param SPF\Response $response
     */
    public function processDynamic(SPF\Request $request, SPF\Response $response)
    {
        $path = $this->document_root . '/' . $request->meta['path'];
        if (is_file($path)) {
            $response->head['Content-Type'] = 'text/html';
            ob_start();
            try {
                include $path;
                $response->body = ob_get_contents();
            } catch (\Exception $e) {
                $response->setHttpStatus(500);
                $response->body = $e->getMessage() . '!<br /><h1>' . self::SOFTWARE . '</h1>';
            }
            ob_end_clean();
        } else {
            $this->httpError(404, $response, "页面不存在({$request->meta['path']})！");
        }
    }
}
