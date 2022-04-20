<?php
namespace SPF\Network;

class Stream
{
    /**
     * 关闭socket
     * @param $socket
     * @param $event
     * @return unknown_type
     */
    static function close($socket,$event=null)
    {
        if($event)
        {
            event_del($event);
            event_free($event);
        }
        fclose($socket);
    }

    /**
     * 非阻塞循环读取，不能用于阻塞socket
     * @param $fp
     * @param $length
     * @return string
     */
    static function read($fp,$length)
    {
        $data = '';
        while($buf = fread($fp, $length))
        {
            $data .= $buf;
            if(strlen($buf) < $length) break;
        }
        return $data;
    }

    static function write($fp, $string)
    {
        $length = strlen($string);
        for($written = 0; $written < $length; $written += $fwrite)
        {
            $fwrite = fwrite($fp, substr($string, $written));
            if($fwrite<=0 or $fwrite===false) return $written;
        }
        return $written;
    }
}
