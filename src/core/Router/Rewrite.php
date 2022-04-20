<?php
namespace SPF\Router;

use SPF;
use SPF\App;
use SPF\IFace\Router;

class Rewrite implements Router
{
    function handle(&$uri)
    {
        $rewrite = SPF\App::getInstance()->config['rewrite'];
        $request = SPF\App::getInstance()->request;

        if (empty($rewrite) or !is_array($rewrite))
        {
            return false;
        }

        $match = array();
        $uri_for_regx = '/' . $uri;
        foreach ($rewrite as $rule)
        {
            if (preg_match('#' . $rule['regx'] . '#i', $uri_for_regx, $match))
            {
                if (isset($rule['get']))
                {
                    $p = explode(',', $rule['get']);
                    foreach ($p as $k => $v)
                    {
                        if (isset($match[$k + 1]))
                        {
                            $request->get[$v] = $match[$k + 1];
                        }
                    }
                }
                $_GET = $request->get;

                return $rule['mvc'];
            }
        }

        return false;
    }
}