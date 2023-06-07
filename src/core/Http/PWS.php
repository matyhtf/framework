<?php
namespace SPF\Http;

use SPF;

/**
 * Class Http_LAMP
 * @package SPF
 */
class PWS implements SPF\IFace\Http
{
    public function header($k, $v)
    {
        $k = ucwords($k);
        SPF\App::getInstance()->response->setHeader($k, $v);
    }

    public function status($code)
    {
        SPF\App::getInstance()->response->setHttpStatus($code);
    }

    public function response($content)
    {
        $this->finish($content);
    }

    public function redirect($url, $mode = 302)
    {
        SPF\App::getInstance()->response->setHttpStatus($mode);
        SPF\App::getInstance()->response->setHeader('Location', $url);
    }

    public function finish($content = null)
    {
        SPF\App::getInstance()->request->finish = 1;
        if ($content) {
            SPF\App::getInstance()->response->body = $content;
        }
        throw new SPF\Exception\Response;
    }

    public function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        SPF\App::getInstance()->response->setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function getRequestBody()
    {
        return SPF\App::getInstance()->request->body;
    }
}
