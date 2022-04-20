<?php
namespace SPF\Http;

use SPF;

class WebSocketParser
{
    public $maxFrameSize = 2000000;
    protected $buffer = '';

    /**
     * @var WebSocketFrame
     */
    protected $frame = null;

    const ERR_TOO_LONG = 10001;

    /**
     * 压入解析队列
     * @param $data
     */
    function push($data)
    {
        $this->buffer .= $data;
    }

    /**
     * 弹出frame
     * @return bool|WebSocketFrame
     * @throws SPF\Http\WebSocketException
     */
    function pop()
    {
        //当前有等待的frame
        if ($this->frame)
        {
            if (strlen($this->buffer) >= $this->frame->length)
            {
                //分包
                $this->frame->data = substr($this->buffer, 0, $this->frame->length);
                self::unMask($this->frame);
                $frame = $this->frame;
                //进入新的frame解析流程
                $this->frame = null;
                $this->buffer = substr($this->buffer, $frame->length);
                return $frame;
            }
            else
            {
                return false;
            }
        }

        $buffer = &$this->buffer;
        if (strlen($buffer) < 2)
        {
            return false;
        }

        $frame = new WebSocketFrame;
        $data_offset = 0;

        //fin:1 rsv1:1 rsv2:1 rsv3:1 opcode:4
        $handle = ord($buffer[$data_offset]);
        $frame->finish = ($handle >> 7) & 0x1;
        $frame->rsv1 = ($handle >> 6) & 0x1;
        $frame->rsv2 = ($handle >> 5) & 0x1;
        $frame->rsv3 = ($handle >> 4) & 0x1;
        $frame->opcode = $handle & 0xf;
        $data_offset++;

        //mask:1 length:7
        $handle = ord($buffer[$data_offset]);
        $frame->mask = ($handle >> 7) & 0x1;

        //0-125
        $frame->length = $handle & 0x7f;
        $length =  &$frame->length;
        $data_offset++;

        //126 short
        if ($length == 0x7e)
        {
            //2 byte
            $handle = unpack('nl', substr($buffer, $data_offset, 2));
            $data_offset += 2;
            $length = $handle['l'];
        }
        //127 int64
        elseif ($length > 0x7e)
        {
            //8 byte
            $handle = unpack('Nh/Nl', substr($buffer, $data_offset, 8));
            $data_offset += 8;
            $length = $handle['l'];
            //超过最大允许的长度了，恶意的连接，需要关闭
            if ($length > $this->maxFrameSize)
            {
                throw new WebSocketException("frame length is too big.", self::ERR_TOO_LONG);
            }
        }

        //mask-key: int32
        if ($frame->mask)
        {
            $frame->mask = array_map('ord', str_split(substr($buffer, $data_offset, 4)));
            $data_offset += 4;
        }

        //把头去掉
        $buffer = substr($buffer, $data_offset);

        //数据长度为0的帧
        if (0 === $length)
        {
            $frame->finish = true;
            $frame->data = '';
            return $frame;
        }

        //完整的一个数据帧
        if (strlen($buffer) >= $length)
        {
            $frame->finish = true;
            $frame->data = substr($buffer, 0, $length);
            //清理buffer
            $buffer = substr($buffer, $length);
            self::unMask($frame);
            return $frame;
        }
        //需要继续等待数据
        else
        {
            $frame->finish = false;
            $this->frame = $frame;
            return false;
        }
    }

    /**
     * @param $frame WebSocketFrame
     */
    static function unMask($frame)
    {
        if ($frame->mask)
        {
            $maskC = 0;
            $data = $frame->data;
            for ($j = 0, $_length = $frame->length; $j < $_length; ++$j)
            {
                $data[$j] = chr(ord($frame->data[$j]) ^ $frame->mask[$maskC]);
                $maskC = ($maskC + 1) % 4;
            }
            $frame->data = $data;
        }
    }
}

class WebSocketFrame
{
    public $finish = false;
    public $opcode;
    public $data;

    public $length;
    public $rsv1;
    public $rsv2;
    public $rsv3;
    public $mask;
}


class WebSocketException extends \Exception
{

}