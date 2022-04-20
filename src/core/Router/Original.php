<?php
namespace SPF\Router;

use SPF\App;
use SPF\IFace\Router;
use SPF\Tool;

class Original implements Router
{
    function handle(&$uri)
    {
        $request = \SPF\App::getInstance()->request;
        $array = App::$default_controller;
        if (!empty($request->get["c"]))
        {
            $array['controller'] = $request->get["c"];
        }
        if (!empty($request->get["v"]))
        {
            $array['view'] = $request->get["v"];
        }
        $request_uri = explode('/', $uri, 3);
        if (count($request_uri) < 2)
        {
            return $array;
        }
        $array['controller'] = $request_uri[0];
        $array['view'] = $request_uri[1];
        Tool::$url_prefix = '';
        if (isset($request_uri[2]))
        {
            $request_uri[2] = trim($request_uri[2], '/');
            $_id = str_replace('.html', '', $request_uri[2]);
            if (is_numeric($_id))
            {
                $request->get['id'] = $_id;
            }
            else
            {
                Tool::$url_key_join = '-';
                Tool::$url_param_join = '-';
                Tool::$url_add_end = '.html';
                Tool::$url_prefix = WEBROOT . "/{$request_uri[0]}/$request_uri[1]/";
                Tool::url_parse_into($request_uri[2], $request->get);
            }
            $_REQUEST = $request->request = array_merge($request->request, $request->get);
            $_GET = $request->get;
        }

        return $array;
    }
}