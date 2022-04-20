<?php
namespace SPF\IFace;

interface Router
{
    /**
     * 返回false会继续调用嗯下一个Router，
     * 返回数组表示命中路由，开始处理请求
     * @param $uri
     * @return mixed
     */
    function handle(&$uri);
}
