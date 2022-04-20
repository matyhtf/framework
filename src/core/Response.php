<?php
namespace SPF;
use SPF;

class Response
{
    public $http_protocol = 'HTTP/1.1';
    public $http_status = 200;

    public $head;
    public $cookie;
    public $body;

    static $HTTP_HEADERS = array(
        100 => "100 Continue",
        101 => "101 Switching Protocols",
        200 => "200 OK",
        201 => "201 Created",
        204 => "204 No Content",
        206 => "206 Partial Content",
        300 => "300 Multiple Choices",
        301 => "301 Moved Permanently",
        302 => "302 Found",
        303 => "303 See Other",
        304 => "304 Not Modified",
        307 => "307 Temporary Redirect",
        400 => "400 Bad Request",
        401 => "401 Unauthorized",
        403 => "403 Forbidden",
        404 => "404 Not Found",
        405 => "405 Method Not Allowed",
        406 => "406 Not Acceptable",
        408 => "408 Request Timeout",
        410 => "410 Gone",
        413 => "413 Request Entity Too Large",
        414 => "414 Request URI Too Long",
        415 => "415 Unsupported Media Type",
        416 => "416 Requested Range Not Satisfiable",
        417 => "417 Expectation Failed",
        500 => "500 Internal Server Error",
        501 => "501 Method Not Implemented",
        503 => "503 Service Unavailable",
        506 => "506 Variant Also Negotiates",
    );

    /**
     * 设置Http状态
     * @param $code
     */
    function setHttpStatus($code)
    {
        $this->head[0] = $this->http_protocol.' '.self::$HTTP_HEADERS[$code];
        $this->http_status = $code;
    }

    /**
     * 设置Http头信息
     * @param $key
     * @param $value
     */
    function setHeader($key,$value)
    {
        $this->head[$key] = $value;
    }

    /**
     * 设置COOKIE
     * @param $name
     * @param null $value
     * @param null $expire
     * @param string $path
     * @param null $domain
     * @param null $secure
     * @param null $httponly
     */
    function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        if ($value == null)
        {
            $value = 'deleted';
        }
        $cookie = "$name=$value";
        if ($expire)
        {
            $cookie .= "; expires=" . date("D, d-M-Y H:i:s T", $expire);
        }
        if ($path)
        {
            $cookie .= "; path=$path";
        }
        if ($secure)
        {
            $cookie .= "; secure";
        }
        if ($domain)
        {
            $cookie .= "; domain=$domain";
        }
        if ($httponly)
        {
            $cookie .= '; httponly';
        }
        $this->cookie[] = $cookie;
    }

    /**
     * 添加http header
     * @param $header
     */
    function addHeaders(array $header)
    {
        $this->head = array_merge($this->head, $header);
    }

    function getHeader($fastcgi = false)
    {
        $out = '';
        if ($fastcgi)
        {
            $out .= 'Status: '.$this->http_status.' '.self::$HTTP_HEADERS[$this->http_status]."\r\n";
        }
        else
        {
            //Protocol
            if (isset($this->head[0]))
            {
                $out .= $this->head[0]."\r\n";
                unset($this->head[0]);
            }
            else
            {
                $out = "HTTP/1.1 200 OK\r\n";
            }
        }
        //fill header
        if (!isset($this->head['Server']))
        {
            $this->head['Server'] = SPF\Protocol\WebServer::SOFTWARE;
        }
        if (!isset($this->head['Content-Type']))
        {
            $this->head['Content-Type'] = 'text/html; charset='.\SPF\App::$charset;
        }
        if (!isset($this->head['Content-Length']))
        {
            $this->head['Content-Length'] = strlen($this->body);
        }
        //Headers
        foreach($this->head as $k=>$v)
        {
            $out .= $k.': '.$v."\r\n";
        }
        //Cookies
        if (!empty($this->cookie) and is_array($this->cookie))
        {
            foreach($this->cookie as $v)
            {
                $out .= "Set-Cookie: $v\r\n";
            }
        }
        //End
        $out .= "\r\n";
        return $out;
    }

    function noCache()
    {
        $this->head['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $this->head['Pragma'] = 'no-cache';
    }
}

