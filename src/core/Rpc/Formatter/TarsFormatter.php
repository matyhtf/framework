<?php

namespace SPF\Rpc\Formatter;

use SPF\Rpc\RpcException;
use SPF\Rpc\Tool\Helper;
use Throwable;

class TarsFormatter implements Formatter
{

    /**
     * 对响应的数据进行encode，然后交由通讯协议进行传输
     * 
     * @param mixed $data
     * @param string $funcName
     * 
     * @return string
     */
    public static function encode($data, $funcName = '')
    {
        $iVersion = 1;
        $cPacketType = 0;
        $iMessageType = 0;
        $iRequestId = 0;
        $statuses = [];
        $servantName = '';
        $iTimeout = 0;

        $context = [];

        $rspBuf = \TUPAPI::encode(
            $iVersion,
            $iRequestId,
            $servantName,
            $funcName,
            $cPacketType,
            $iMessageType,
            $iTimeout,
            $context,
            $statuses,
            $data
        );

        return $rspBuf;
    }

    public static function decode($buffer)
    {
        // 接下来解码
        $decodeRet = \TUPAPI::decode($buffer, 1);
        if ($decodeRet['iRet'] !== 0) {
            // TODO
            // $msg = isset($decodeRet['sResultDesc']) ? $decodeRet['sResultDesc'] : "";
            // throw new \Exception($msg, $decodeRet['iRet']);
        }
        $sBuffer = $decodeRet['sBuffer'];

        return $sBuffer;
    }

    /**
     * 对响应的数据进行encode，然后交由通讯协议进行传输
     * 
     * @param mixed $response
     * @param string $funcName
     * 
     * @return string
     */
    public static function encodeResponse($response, $request)
    {
        $iVersion = 1;
        $cPacketType = 0;
        $iMessageType = 0;
        $iRequestId = 0;
        $statuses = [];
        $iRet = 0;
        $sResultDesc = '';

        $buffers = [];
        if ($request['func_params']['return']['type'] !== 'void') {
            $buffers[] = self::packBuffer($request['func_params']['return']['type'], $response, 0);
        }

        // &取地址符参数输出
        foreach($request['func_params']['params'] as $param) {
            if ($param['ref']) {
                $value = $request['req_params'][$param['index']];
                $buffers[] = self::packBuffer($param['type'], $value, $param['index'] + 1);
            }
        }

        $rspBuf = \TUPAPI::encodeRspPacket(
            $iVersion,
            $cPacketType,
            $iMessageType,
            $iRequestId,
            $iRet,
            $sResultDesc,
            $buffers,
            $statuses
        );

        return $rspBuf;
    }

    /**
     * 对通讯协议获取的请求数据进行decode
     * 
     * @param string $buffer
     * 
     * @return mixed
     */
    public static function decodeRequest($buffer)
    {
        try {
            $unpackResult = \TUPAPI::decodeReqPacket($buffer);
            // TODO decode失败的异常处理

            $parsedFunc = Helper::parserFuncName($unpackResult['sFuncName']);
            $reqParams = self::convertToArgs($parsedFunc['params'], $unpackResult);

            return [
                'class' => $parsedFunc['class'],
                'function' => $parsedFunc['function'],
                'func_params' => $parsedFunc['params'],
                'req_params' => $reqParams,
            ];
        } catch (RpcException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RpcException(RpcException::ERR_INVALID_TARS, ['error' => $e->getMessage()]);
        }
    }

    // 完成了对入包的decode之后,获取到了sBuffer
    protected static function convertToArgs($params, $unpackResult)
    {
        try {
            $sBuffer = $unpackResult['sBuffer'];

            $unpackMethods = [
                'bool' => '\TUPAPI::getBool',
                'byte' => '\TUPAPI::getChar',
                'char' => '\TUPAPI::getChar',
                'unsigned byte' => '\TUPAPI::getUInt8',
                'unsigned char' => '\TUPAPI::getUInt8',
                'short' => '\TUPAPI::getShort',
                'unsigned short' => '\TUPAPI::getUInt16',
                'int' => '\TUPAPI::getInt32',
                'unsigned int' => '\TUPAPI::getUInt32',
                'long' => '\TUPAPI::getInt64',
                'float' => '\TUPAPI::getFloat',
                'double' => '\TUPAPI::getDouble',
                'string' => '\TUPAPI::getString',
                'enum' => '\TUPAPI::getShort',
                'map' => '\TUPAPI::getMap',
                'vector' => '\TUPAPI::getVector',
                'struct' => '\TUPAPI::getStruct',
            ];

            $args = [];
            foreach ($params['params'] as $param) {
                $type = $param['type'];
                $unpackMethod = $unpackMethods[$type];

                // 需要判断是否是简单类型,还是vector或map或struct
                if ($type === 'map' || $type === 'vector') {
                    if ($param['ref']) {
                        ${$param['name']} = self::createInstance($param['proto']);
                        $args[] = &${$param['name']};
                    } else {
                        // 对于复杂的类型,需要进行实例化
                        $proto = self::createInstance($param['proto']);
                        $args[] = $unpackMethod($param['index'] + 1, $proto, $sBuffer, false, 1);
                        // $args[] = $unpackMethod($param['name'], $proto, $sBuffer, false, 3);
                    }
                } elseif ($type === 'struct') {
                    if ($param['ref']) {
                        ${$param['name']} = new $param['proto']();
                        $args[] = &${$param['name']};
                    } else {
                        // 对于复杂的类型,需要进行实例化
                        $proto = new $param['proto']();
                        $value = $unpackMethod($param['index'] + 1, $proto, $sBuffer, false, 1);
                        // $value = $unpackMethod($param['name'], $proto, $sBuffer, false, 3);
                        self::fromArray($value, $proto);
                        $args[] = $proto;
                    }
                } else {
                    if ($param['ref']) {
                        ${$param['name']} = $unpackMethod($param['index'] + 1, $sBuffer, false, 1);
                        $args[] = &${$param['name']};
                    } else {
                        $args[] = $unpackMethod($param['index'] + 1, $sBuffer, false, 1);
                        // $args[] = $unpackMethod($param['name'], $sBuffer, false, 3);
                    }
                }
            }

            return $args;
        } catch (Throwable $e) {
            $message = $e->getMessage();
            throw new RpcException(RpcException::ERR_INVALID_TARS, ['message' => $message], $message);
        }
    }

    private static function createInstance($proto)
    {
        if (self::isBasicType($proto)) {
            return self::convertBasicType($proto);
        } elseif (!strpos($proto, '(')) {
            $structInst = new $proto();

            return $structInst;
        } else {
            $pos = strpos($proto, '(');
            $className = substr($proto, 0, $pos);
            if ($className == '\TARS_Vector') {
                $next = trim(substr($proto, $pos, strlen($proto) - $pos), '()');
                $args[] = self::createInstance($next);
            } elseif ($className == '\TARS_Map') {
                $next = trim(substr($proto, $pos, strlen($proto) - $pos), '()');
                $pos = strpos($next, ',');
                $left = substr($next, 0, $pos);
                $right = trim(substr($next, $pos, strlen($next) - $pos), ',');

                $args[] = self::createInstance($left);
                $args[] = self::createInstance($right);
            } elseif (self::isBasicType($className)) {
                $next = trim(substr($proto, $pos, strlen($proto) - $pos), '()');
                $basicInst = self::createInstance($next);
                $args[] = $basicInst;
            } else {
                $structInst = new $className();
                $args[] = $structInst;
            }
            $ins = new $className(...$args);
        }

        return $ins;
    }

    private static function isBasicType($type)
    {
        $basicTypes = [
            '\TARS::BOOL',
            '\TARS::CHAR',
            '\TARS::CHAR',
            '\TARS::UINT8',
            '\TARS::UINT8',
            '\TARS::SHORT',
            '\TARS::UINT16',
            '\TARS::INT32',
            '\TARS::UINT32',
            '\TARS::INT64',
            '\TARS::FLOAT',
            '\TARS::DOUBLE',
            '\TARS::STRING',
            '\TARS::INT32',
        ];

        return in_array($type, $basicTypes);
    }

    private static function convertBasicType($type)
    {
        $basicTypes = [
            '\TARS::BOOL' => 1,
            '\TARS::CHAR' => 2,
            '\TARS::UINT8' => 3,
            '\TARS::SHORT' => 4,
            '\TARS::UINT16' => 5,
            '\TARS::FLOAT' => 6,
            '\TARS::DOUBLE' => 7,
            '\TARS::INT32' => 8,
            '\TARS::UINT32' => 9,
            '\TARS::INT64' => 10,
            '\TARS::STRING' => 11,
        ];

        return $basicTypes[$type];
    }

    // 将数组转换成对象
    private static function fromArray($data, &$structObj)
    {
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (method_exists($structObj, 'set' . ucfirst($key))) {
                    call_user_func_array([$this, 'set' . ucfirst($key)], [$value]);
                } elseif ($structObj->$key instanceof \TARS_Struct) {
                    self::fromArray($value, $structObj->$key);
                } else {
                    $structObj->$key = $value;
                }
            }
        }
    }

    protected static function packBuffer($type, $value, $index)
    {
        $packMethods = [
            'bool' => '\TUPAPI::putBool',
            'byte' => '\TUPAPI::putChar',
            'char' => '\TUPAPI::putChar',
            'unsigned byte' => '\TUPAPI::putUInt8',
            'unsigned char' => '\TUPAPI::putUInt8',
            'short' => '\TUPAPI::putShort',
            'unsigned short' => '\TUPAPI::putUInt16',
            'int' => '\TUPAPI::putInt32',
            'unsigned int' => '\TUPAPI::putUInt32',
            'long' => '\TUPAPI::putInt64',
            'float' => '\TUPAPI::putFloat',
            'double' => '\TUPAPI::putDouble',
            'string' => '\TUPAPI::putString',
            'enum' => '\TUPAPI::putShort',
            'map' => '\TUPAPI::putMap',
            'vector' => '\TUPAPI::putVector',
            'struct' => '\TUPAPI::putStruct',
        ];

        $packMethod = $packMethods[$type];

        return $packMethod($index, $value, 1);
    }
}