<?php
namespace SPF\Memory;

class Stream
{
    protected $position;
    protected $varname;

    public static $memory = array();

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $url = parse_url($path);
        $this->varname = $url["host"];
        $this->position = 0;
        if (empty(self::$memory[$this->varname])) {
            self::$memory[$this->varname] = '';
        }
        return true;
    }

    public function stream_read($count)
    {
        $ret = substr(self::$memory[$this->varname], $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    public function stream_write($data)
    {
        $left = substr(self::$memory[$this->varname], 0, $this->position);
        $right = substr(self::$memory[$this->varname], $this->position + strlen($data));
        self::$memory[$this->varname] = $left . $data . $right;
        $this->position += strlen($data);

        return strlen($data);
    }

    public function stream_tell()
    {
        return $this->position;
    }

    public function stream_eof()
    {
        return $this->position >= strlen(self::$memory[$this->varname]);
    }

    public function stream_seek($offset, $whence)
    {
        switch ($whence) {
            case SEEK_SET:
                if ($offset < strlen(self::$memory[$this->varname]) && $offset >= 0) {
                    $this->position = $offset;

                    return true;
                } else {
                    return false;
                }
                break;

            case SEEK_CUR:
                if ($offset >= 0) {
                    $this->position += $offset;

                    return true;
                } else {
                    return false;
                }
                break;

            case SEEK_END:
                if (strlen(self::$memory[$this->varname]) + $offset >= 0) {
                    $this->position = strlen(self::$memory[$this->varname]) + $offset;

                    return true;
                } else {
                    return false;
                }
                break;

            default:
                return false;
        }
    }

    public function stream_metadata($path, $option, $var)
    {
        if ($option == STREAM_META_TOUCH) {
            $url = parse_url($path);
            $varname = $url["host"];
            if (!isset(self::$memory[$varname])) {
                self::$memory[$varname] = '';
            }
            return true;
        }
        return false;
    }
}
