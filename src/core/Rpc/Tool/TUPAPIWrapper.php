<?php

namespace SPF\Rpc\Tool;

use SPF\Rpc\RpcException;

class TUPAPIWrapper
{
    /**
     * TUP协议的版本
     *
     * @var int
     */
    public static $version = 1;

    public static function putBool($paramName, $tag, $bool)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getBool($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putChar($paramName, $tag, $char)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getChar($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putUInt8($paramName, $tag, $uint8)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getUint8($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putShort($paramName, $tag, $short)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getShort($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putUInt16($paramName, $tag, $uint16)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getUint16($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putInt32($paramName, $tag, $int)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getInt32($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putUint32($paramName, $tag, $uint)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getUint32($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putInt64($paramName, $tag, $bigint)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getInt64($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putDouble($paramName, $tag, $double)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getDouble($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putFloat($paramName, $tag, $float)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getFloat($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putString($paramName, $tag, $string)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getString($name, $tag, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putVector($paramName, $tag, $vec)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getVector($name, $tag, $vec, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putMap($paramName, $tag, $map)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getMap($name, $tag, $obj, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function putStruct($paramName, $tag, $obj)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    public static function getStruct($name, $tag, &$obj, $sBuffer, $required = true)
    {
        return static::proxyTUPAPI(__FUNCTION__, ...func_get_args());
    }

    /**
     * 将数组转换成对象
     *
     * @param array $data
     * @param \TARS_Struct $structObj
     */
    public static function fromArray($data, &$structObj)
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if ($structObj->$key instanceof \TARS_Struct) {
                    self::fromArray($value, $structObj->$key);
                } else {
                    $structObj->$key = $value;
                }
            }
        }
    }

    /**
     * TUPAPI代理
     *
     * @return mixed
     */
    protected static function proxyTUPAPI()
    {
        try {
            $args = func_get_args();
            $method = array_shift($args);
            $call = "\\TUPAPI::{$method}";

            if (static::$version == 1) {
                // version 1
                // 扔掉第一个参数name
                array_shift($args);
                $tag = array_shift($args);
            } else {
                // version 3
                // 扔掉第二个参数index
                $tag = array_shift($args);
                array_shift($args);
            }

            array_unshift($args, $tag);
            array_push($args, static::$version);

            return $call(...$args);
        } catch (\TARS_Exception $e) {
            $code = $e->getCode();
            $rpcCode = static::getTarsException($code);
            throw new RpcException($rpcCode, ['message' => $e->getMessage(), 'code' => $code]);
        } catch (\Throwable $e) {
            throw new RpcException(RpcException::ERR_CLIENT_UNKNOWN, ['message' => $e->getMessage(), 'code' => $code]);
        }
    }

    /**
     * 获取TARS_Exception异常对应的异常信息
     *
     * @param int $code
     *
     * @return int
     */
    protected static function getTarsException($code)
    {
        $map = [
            10001 => RpcException::ERR_TARS_CANNOT_CONVERT,
            10002 => RpcException::ERR_TARS_OUTOF_RANGE,
            10003 => RpcException::ERR_TARS_MALLOC_FAILED,
            10004 => RpcException::ERR_TARS_CLASS_UNINIT,
            10005 => RpcException::ERR_TARS_REQUIRED_FIELD_LOST,
            10006 => RpcException::ERR_TARS_DATA_FORMAT_ERROR,
            10007 => RpcException::ERR_TARS_TYPE_INVALID,
            10008 => RpcException::ERR_TARS_CLASS_MISMATCH,
            10009 => RpcException::ERR_TARS_WRONG_PARAMS,
            10010 => RpcException::ERR_TARS_STATIC_FIELDS_PARAM_LOST,
            10011 => RpcException::ERR_TARS_ARRAY_RETRIEVE,
            10012 => RpcException::ERR_TARS_READ_MAP_ERROR,
            10013 => RpcException::ERR_TARS_SET_CONTEXT_ERROR,
            10014 => RpcException::ERR_TARS_SET_STATUS_ERROR,
            10015 => RpcException::ERR_TARS_ENCODE_BUF_ERROR,
            10016 => RpcException::ERR_TARS_WRITE_IVERSION_ERROR,
            10017 => RpcException::ERR_TARS_WRITE_CPACKETTYPE_ERROR,
            10018 => RpcException::ERR_TARS_WRITE_IMESSAGETYPE_ERROR,
            10019 => RpcException::ERR_TARS_WRITE_IREQUESTID_ERROR,
            10020 => RpcException::ERR_TARS_WRITE_SSERVANTNAME_ERROR,
            10021 => RpcException::ERR_TARS_WRITE_SFUNCNAME_ERROR,
            10022 => RpcException::ERR_TARS_WRITE_SBUFFER_ERROR,
            10023 => RpcException::ERR_TARS_WRITE_ITIMEOUT_ERROR,
            10024 => RpcException::ERR_TARS_WRITE_CONTEXT_ERROR,
            10025 => RpcException::ERR_TARS_WRITE_STATUS_ERROR,
            10026 => RpcException::ERR_TARS_STRUCT_COMPLICATE_NOT_DEFINE,
            10027 => RpcException::ERR_TARS_VECOTR_OR_MAP_EXT_PARAM_LOST,
            10028 => RpcException::ERR_TARS_STATIC_NAME_NOT_STRING_ERROR,
            10029 => RpcException::ERR_TARS_STATIC_REQUIRED_NOT_BOOL_ERROR,
            10030 => RpcException::ERR_TARS_STATIC_TYPE_NOT_LONG_ERROR,
        ];
        
        return $map[$code] ?? RpcException::ERR_TARS_UNKNOWN;
    }
}
