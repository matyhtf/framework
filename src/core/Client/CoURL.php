<?php
namespace SPF\Client;

class CoURL
{
    protected $handle;
    protected $requests;

    const METHOD_GET = 1;
    const METHOD_POST = 2;

    function __construct()
    {
        $this->handle = curl_multi_init();
    }

    /**
     * GET请求
     * @param $url
     * @param null $callback
     * @return CoURLResult
     */
    function get($url, $callback = null)
    {
        $ret = new CoURLResult($url, $callback, $this->handle);
        $ret->method = self::METHOD_GET;
        $this->requests[$ret->id] = $ret;
        return $ret;
    }

    /**
     * POST请求
     * @param $url
     * @param null $callback
     * @return CoURLResult
     */
    function post($url, $data, $callback = null)
    {
        $ret = new CoURLResult($url, $callback, $this->handle);
        $ret->method = self::METHOD_POST;
        $ret->data = $data;
        $this->requests[$ret->id] = $ret;
        return $ret;
    }

    function wait($timeout = 2)
    {
        foreach($this->requests as $req)
        {
            /**
             * @var CoURLResult $req
             */
            $req->execute();
        }

        $mhandle = $this->handle;
        $n = 0;
        $active = true;

        while ($active)
        {
            if (($status = curl_multi_exec($mhandle, $active)) != CURLM_CALL_MULTI_PERFORM)
            {
                if ($status != CURLM_OK)
                {
                    break;
                }
                //如果没有准备就绪，就再次调用curl_multi_exec
                while ($done = curl_multi_info_read($mhandle))
                {
                    $ch = $done["handle"];
                    $key = intval($ch);
                    /**
                     * @var $retObj CoURLResult
                     */
                    $retObj = $this->requests[$key];
                    $retObj->info = curl_getinfo($ch);
                    $retObj->error = curl_error($ch);
                    $retObj->result = curl_multi_getcontent($ch);
                    if ($retObj->callback)
                    {
                        call_user_func($retObj->callback, $retObj);
                    }
                    curl_multi_remove_handle($mhandle, $ch);
                    curl_close($ch);
                    unset($this->requests[$key]);
                    $n ++;
                    if ($active > 0)
                    {
                        curl_multi_select($mhandle, $timeout);
                    }
                }
            }
        }
        curl_multi_close($mhandle);
        return $n;
    }
}
