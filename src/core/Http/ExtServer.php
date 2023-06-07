<?php
namespace SPF\Http;

use SPF;
use SPF\Coroutine\BaseContext as Context;
use SPF\Validator\Validator;

/**
 * Class Http_LAMP
 * @package SPF
 */
class ExtServer extends SPF\Protocol\Base implements SPF\IFace\Http
{
    /**
     * @var \swoole_http_request
     */
    public $request;

    /**
     * @var \swoole_http_response
     */
    public $response;

    public $document_root;
    public $charset = 'utf-8';
    public $expire_time = 86400;
    const DATE_FORMAT_HTTP = 'D, d-M-Y H:i:s T';

    protected $mimes;
    protected $types;
    protected $config;

    public static $gzip_extname = array('js' => true, 'css' => true, 'html' => true, 'txt' => true);
    public static $userRouter;
    public static $clientEnv = null;

    public function __construct($config)
    {
        $mimes = require dirname(dirname(__DIR__)) . '/data/mimes.php';
        $this->mimes = $mimes;
        $this->types = array_flip($mimes);

        if (!empty($config['document_root'])) {
            $this->document_root = trim($config['document_root']);
        }
        if (!empty($config['charset'])) {
            $this->charset = trim($config['charset']);
        }
        $this->config = $config;
    }

    protected function getRequest()
    {
        if (SPF\App::$enableCoroutine) {
            return Context::get('request');
        } else {
            return $this->request;
        }
    }

    protected function getResponse()
    {
        if (SPF\App::$enableCoroutine) {
            return Context::get('response');
        } else {
            return $this->response;
        }
    }

    public function header($k, $v)
    {
        $k = ucwords($k);
        $this->getResponse()->header($k, $v);
    }

    public function status($code)
    {
        $this->getResponse()->status($code);
    }

    public function response($content)
    {
        $this->finish($content);
    }

    public function redirect($url, $mode = 302)
    {
        $this->getResponse()->status($mode);
        $this->getResponse()->header('Location', $url);
    }

    public function finish($content = '')
    {
        throw new SPF\Exception\Response($content);
    }

    public function getRequestBody()
    {
        return $this->getRequest()->rawContent();
    }

    public function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        $this->getResponse()->cookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * 将swoole扩展产生的请求对象数据赋值给框架的Request对象
     * @param SPF\Request $request
     */
    public function assign(SPF\Request $request)
    {
        $_request = $this->getRequest();
        if (!empty($_request->get)) {
            $request->get = $_request->get;
        }
        if (!empty($_request->post)) {
            $request->post = $_request->post;
        }
        if (!empty($_request->files)) {
            $request->files = $_request->files;
        }
        if (!empty($_request->cookie)) {
            $request->cookie = $_request->cookie;
        }
        if (!empty($_request->server)) {
            foreach ($_request->server as $key => $value) {
                $request->server[strtoupper($key)] = $value;
            }
            $request->remote_ip = $_request->server['remote_addr'];
        }
        $request->header = $_request->header;
        $request->setGlobal();
    }

    public function doStatic(\swoole_http_request $req, \swoole_http_response $resp)
    {
        $file = $this->document_root . $req->server['request_uri'];
        $read_file = true;
        $fstat = stat($file);

        //过期控制信息
        if (isset($req->header['if-modified-since'])) {
            $lastModifiedSince = strtotime($req->header['if-modified-since']);
            if ($lastModifiedSince and $fstat['mtime'] <= $lastModifiedSince) {
                //不需要读文件了
                $read_file = false;
                $resp->status(304);
            }
        } else {
            $resp->header('Cache-Control', "max-age={$this->expire_time}");
            $resp->header('Pragma', "max-age={$this->expire_time}");
            $resp->header('Last-Modified', date(self::DATE_FORMAT_HTTP, $fstat['mtime']));
            $resp->header('Expires', "max-age={$this->expire_time}");
        }

        if ($read_file) {
            $extname = SPF\Upload::getFileExt($file);
            if (empty($this->types[$extname])) {
                $mime_type = 'text/html; charset='.$this->charset;
            } else {
                $mime_type = $this->types[$extname];
            }
            $resp->header('Content-Type', $mime_type);
            $resp->sendfile($file);
        } else {
            $resp->end();
        }
        return true;
    }

    public function setRouter($function)
    {
        if (!is_callable($function)) {
            throw  new \RuntimeException("function:$function is not callable", 1);
        }
        self::$userRouter = $function;
    }

    public function swooleRouter($request)
    {
        $php = SPF\App::getInstance();
        $php->request = new SPF\Request();
        $php->response = new SPF\Response();
        $this->assign($php->request);
        if (SPF\App::$enableOutputBuffer) {
            ob_start();
            /*---------------------处理MVC----------------------*/
            $body = $php->handle();
            $echo_output = ob_get_contents();
            ob_end_clean();
            $body = $echo_output.$body;
        } else {
            $body = $php->handle();
        }
        return $body;
    }

    public static function setEnv($request)
    {
        self::$clientEnv = null;//reset last env
        self::$clientEnv['server'] = $request->cookie;
        self::$clientEnv['cookie'] = $request->server;
    }

    public function onRequest(\swoole_http_request $req, \swoole_http_response $resp)
    {
        if ($this->document_root and is_file($this->document_root . $req->server['request_uri'])) {
            $this->doStatic($req, $resp);
            return;
        }

        $this->request = $req;
        $this->response = $resp;

        //保存协程上下文
        if (SPF\App::$enableCoroutine) {
            Context::put('request', $req);
            Context::put('response', $resp);
        }
        self::setEnv($req);
        try {
            try {
                if (!empty(self::$userRouter)) {
                    $body = call_user_func_array(self::$userRouter, [$req]);
                } else {
                    $body = $this->swooleRouter($req);
                }
                if (!isset($resp->header['Cache-Control'])) {
                    $resp->header('Cache-Control', 'no-store, no-cache, must-revalidate');
                }
                if (!isset($resp->header['Pragma'])) {
                    $resp->header('Pragma', 'no-cache');
                }
                $resp->end($body);
            } catch (SPF\Exception\Response $e) {
                $resp->end($e->getMessage());
            }
        } catch (\Exception $e) {
            $resp->status(500);
            $resp->end($e->getMessage() . "<hr />" . nl2br($e->getTraceAsString()));
        }
        //保存协程上下文
        if (SPF\App::$enableCoroutine) {
            Context::delete('request');
            Context::delete('response');
        }
    }

    public function __clean()
    {
        $php = SPF\App::getInstance();
        //模板初始化
        if (!empty($php->tpl)) {
            $php->tpl->clear_all_assign();
        }
    }

    /**
     * 验证请求参数是否合法
     *
     * @param string $class
     * @param string $method
     * @param array $args
     */
    protected static function validateRequest($class, $method, $args)
    {
        $method = strtolower($method);

        $map = Validator::getValidateMap();
        if (!isset($map[$class]) || empty($map[$class][$method])) {
            return;
        }

        Validator::validate($args, $map[$class][$method]);
    }
}
