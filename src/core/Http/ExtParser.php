<?php
namespace SPF\Http;

/**
 * Class ExtParser
 * 使用pecl_http扩展
 * @package SPF\Http
 */
class ExtParser implements \SPF\IFace\HttpParser
{
    public function parseHeader($header)
    {
        $head =  http_parse_headers($header);
        if ($head === false) {
            return false;
        } else {
            $head[0]['protocol'] = "HTTP/1.1";
            $head[0]['uri'] = $head["Request Url"];
            $head[0]['method'] = $head["Request Method"];
        }
        return $head;
    }
    public function parseBody($request)
    {
        $params = array();
        parse_str($request->body, $params);
        return $params;
    }
    public function parseCookie($request)
    {
        $cookie =  http_parse_cookie($request->head['Cookie']);
        if (isset($cookie->cookies)) {
            return $cookie->cookies;
        } else {
            return array();
        }
    }
}
