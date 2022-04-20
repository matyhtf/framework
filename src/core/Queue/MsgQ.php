<?php

namespace SPF\Queue;

use SPF;

/**
 * 是对Linux Sysv系统消息队列的封装，单台服务器推荐使用
 * @author Tianfeng.Han
 */
class MsgQ implements SPF\IFace\Queue
{
    protected $msgid;
    protected $msgtype = 1;
    protected $msg;

    function __construct($config)
    {
        if (!empty($config['msgid'])) {
            $this->msgid = $config['msgid'];
        } else {
            $this->msgid = ftok(__FILE__, 0);
        }

        if (!empty($config['msgtype'])) {
            $this->msgtype = $config['msgtype'];
        }

        $this->msg = msg_get_queue($this->msgid);
    }

    function pop()
    {
        $ret = msg_receive($this->msg, 0, $this->msgtype, 65525, $data);
        if ($ret) {
            return $data;
        }
        return false;
    }

    function push($data)
    {
        return msg_send($this->msg, $this->msgtype, $data);
    }
}
