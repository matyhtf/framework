<?php
namespace SPF;

class Request
{
    /**
     * 文件描述符
     * @var int
     */
    public $fd;
    public $id;

    /**
     * 请求时间
     * @var int
     */
    public $time;

    /**
     * 客户端IP
     * @var
     */
    public $remote_ip;

    /**
     * 客户端PORT
     * @var
     */
    public $remote_port;

    public $get = array();
    public $post = array();
    public $files = array();
    public $cookie = array();
    public $session = array();
    public $request;
    public $server = array();

    /**
     * @var \StdClass
     */
    public $attrs;

    public $header = array();
    public $body;
    public $meta = array();

    public $finish = false;
    public $ext_name;
    public $status;

    /**
     * 将原始请求信息转换到PHP超全局变量中
     */
    function setGlobal()
    {
        /**
         * 将HTTP头信息赋值给$_SERVER超全局变量
         */
        foreach ($this->header as $key => $value)
        {
            $_key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $this->server[$_key] = $value;
        }

        $_GET = $this->get;
        $_POST = $this->post;
        $_FILES = $this->files;
        $_COOKIE = $this->cookie;
        $_SERVER = $this->server;

        $this->request = $_REQUEST = array_merge($this->get, $this->post, $this->cookie);
    }

    /**
     * LAMP环境初始化
     */
    function initWithFastCGI()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->cookie = $_COOKIE;
        $this->server = $_SERVER;
        $this->request = $_REQUEST;
    }

    function unsetGlobal()
    {
        $_REQUEST = $_SESSION = $_COOKIE = $_FILES = $_POST = $_SERVER = $_GET = array();
    }

    function isWebSocket()
    {
        return isset($this->header['Upgrade']) && strtolower($this->header['Upgrade']) == 'websocket';
    }

    /**
     * 跳转网址
     * @param $url
     */
    public function redirect($url, $mode = 302)
    {
        App::getInstance()->http->redirect($url, $mode);
    }

    /**
     * 发送下载声明
     */
    function download($mime, $filename)
    {
        App::getInstance()->http->header('Content-type', $mime);
        App::getInstance()->http->header('Content-Disposition', "attachment; filename=$filename");
    }

    /**
     * 获取客户端IP
     * @return string
     */
    function getClientIP()
    {
        if (isset($this->server["HTTP_X_REAL_IP"]) and strcasecmp($this->server["HTTP_X_REAL_IP"], "unknown"))
        {
            return $this->server["HTTP_X_REAL_IP"];
        }
        if (isset($this->server["HTTP_CLIENT_IP"]) and strcasecmp($this->server["HTTP_CLIENT_IP"], "unknown"))
        {
            return $this->server["HTTP_CLIENT_IP"];
        }
        if (isset($this->server["HTTP_X_FORWARDED_FOR"]) and strcasecmp($this->server["HTTP_X_FORWARDED_FOR"], "unknown"))
        {
            return $this->server["HTTP_X_FORWARDED_FOR"];
        }
        if (isset($this->server["REMOTE_ADDR"]))
        {
            return $this->server["REMOTE_ADDR"];
        }
        return "";
    }

    /**
     * 获取客户端浏览器信息
     * @return string
     */
    function getBrowser()
    {
        $sys = $this->server['HTTP_USER_AGENT'];
        if (stripos($sys, "Firefox/") > 0)
        {
            preg_match("/Firefox\/([^;)]+)+/i", $sys, $b);
            $exp[0] = "Firefox";
            $exp[1] = $b[1];
        }
        elseif (stripos($sys, "Maxthon") > 0)
        {
            preg_match("/Maxthon\/([\d\.]+)/", $sys, $aoyou);
            $exp[0] = "傲游";
            $exp[1] = $aoyou[1];
        }
        elseif (stripos($sys, "MSIE") > 0)
        {
            preg_match("/MSIE\s+([^;)]+)+/i", $sys, $ie);
            $exp[0] = "IE";
            $exp[1] = $ie[1];
        }
        elseif (stripos($sys, "OPR") > 0)
        {
            preg_match("/OPR\/([\d\.]+)/", $sys, $opera);
            $exp[0] = "Opera";
            $exp[1] = $opera[1];
        }
        elseif (stripos($sys, "Edge") > 0)
        {
            preg_match("/Edge\/([\d\.]+)/", $sys, $Edge);
            $exp[0] = "Edge";
            $exp[1] = $Edge[1];
        }
        elseif (stripos($sys, "Chrome") > 0)
        {
            preg_match("/Chrome\/([\d\.]+)/", $sys, $google);
            $exp[0] = "Chrome";
            $exp[1] = $google[1];
        }
        elseif (stripos($sys, 'rv:') > 0 && stripos($sys, 'Gecko') > 0)
        {
            preg_match("/rv:([\d\.]+)/", $sys, $IE);
            $exp[0] = "IE";
            $exp[1] = $IE[1];
        }
        else
        {
            $exp[0] = "Unkown";
            $exp[1] = "";
        }

        return $exp[0] . '(' . $exp[1] . ')';
    }
    /**
     * 获取客户端操作系统信息
     * @return string
     */
    function getOS()
    {
        $agent = $this->server['HTTP_USER_AGENT'];
        if (preg_match('/win/i', $agent) && strpos($agent, '95'))
        {
            $os = 'Windows 95';
        }
        elseif (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90'))
        {
            $os = 'Windows ME';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/98/i', $agent))
        {
            $os = 'Windows 98';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent))
        {
            $os = 'Windows Vista';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent))
        {
            $os = 'Windows 7';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent))
        {
            $os = 'Windows 8';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent))
        {
            $os = 'Windows 10';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent))
        {
            $os = 'Windows XP';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent))
        {
            $os = 'Windows 2000';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent))
        {
            $os = 'Windows NT';
        }
        elseif (preg_match('/win/i', $agent) && preg_match('/32/i', $agent))
        {
            $os = 'Windows 32';
        }
        elseif (preg_match('/linux/i', $agent))
        {
            $os = 'Linux';
        }
        elseif (preg_match('/unix/i', $agent))
        {
            $os = 'Unix';
        }
        elseif (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'SunOS';
        }
        elseif (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'IBM OS/2';
        }
        elseif (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent))
        {
            $os = 'Macintosh';
        }
        elseif (preg_match('/PowerPC/i', $agent))
        {
            $os = 'PowerPC';
        }
        elseif (preg_match('/AIX/i', $agent))
        {
            $os = 'AIX';
        }
        elseif (preg_match('/HPUX/i', $agent))
        {
            $os = 'HPUX';
        }
        elseif (preg_match('/NetBSD/i', $agent))
        {
            $os = 'NetBSD';
        }
        elseif (preg_match('/BSD/i', $agent))
        {
            $os = 'BSD';
        }
        elseif (preg_match('/OSF1/i', $agent))
        {
            $os = 'OSF1';
        }
        elseif (preg_match('/IRIX/i', $agent))
        {
            $os = 'IRIX';
        }
        elseif (preg_match('/FreeBSD/i', $agent))
        {
            $os = 'FreeBSD';
        }
        elseif (preg_match('/teleport/i', $agent))
        {
            $os = 'teleport';
        }
        elseif (preg_match('/flashget/i', $agent))
        {
            $os = 'flashget';
        }
        elseif (preg_match('/webzip/i', $agent))
        {
            $os = 'webzip';
        }
        elseif (preg_match('/offline/i', $agent))
        {
            $os = 'offline';
        }
        else
        {
            $os = 'Unknown';
        }

        return $os;
    }
}
