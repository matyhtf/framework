<?php
namespace SPF\Http;

use SPF;

/**
 * Class Http_LAMP
 * @package SPF
 */
class PWS implements SPF\IFace\Http
{
    function header($k, $v)
    {
        $k = ucwords($k);
        SPF\App::getInstance()->response->setHeader($k, $v);
    }

    function status($code)
    {
        SPF\App::getInstance()->response->setHttpStatus($code);
    }

    function response($content)
    {
        $this->finish($content);
    }

    function redirect($url, $mode = 302)
    {
        SPF\App::getInstance()->response->setHttpStatus($mode);
        SPF\App::getInstance()->response->setHeader('Location', $url);
    }

    function finish($content = null)
    {
        SPF\App::getInstance()->request->finish = 1;
        if ($content)
        {
            SPF\App::getInstance()->response->body = $content;
        }
        throw new SPF\Exception\Response;
    }

    function setcookie($name, $value = null, $expire = null, $path = '/', $domain = null, $secure = null, $httponly = null)
    {
        SPF\App::getInstance()->response->setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    function getRequestBody()
    {
        return SPF\App::getInstance()->request->body;
    }
}
