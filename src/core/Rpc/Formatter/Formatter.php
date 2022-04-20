<?php

namespace SPF\Rpc\Formatter;

interface Formatter
{
    /**
     * 对响应的数据进行encode，然后交由通讯协议进行传输
     * 
     * @param mixed $data
     * @param string $funcName
     * 
     * @return string
     */
    public static function encode($data, $funcName = '');

    /**
     * 对通讯协议获取的请求数据进行decode
     * 
     * @param string $buffer
     * 
     * @return mixed
     */
    public static function decode($buffer);
}
