<?php

namespace SPF\Rpc;

use SPF\Exception\Exception;

class RpcException extends Exception
{
    const ERR_SUCCESS = 0; // 成功

    const ERR_HEADER = 9001; //错误的包头
    const ERR_TOOBIG = 9002; //请求包体长度超过允许的范围
    const ERR_SERVER_BUSY = 9003; //服务器繁忙，超过处理能力

    const ERR_UNPACK = 9204; // 解包失败
    const ERR_PARAMS = 9205; // 参数错误
    const ERR_NOFUNC = 9206; // 函数不存在
    const ERR_CALL = 9207; // 执行错误
    const ERR_ACCESS_DENY = 9208; // 访问被拒绝，客户端主机未被授权
    const ERR_USER = 9209; // 用户名密码错误
    const ERR_UNSUPPORT_FMT = 9210; // 不支持的打包格式
    const ERR_INVALID_TARS = 9211; // tars包数据不合法

    const ERR_SEND = 9301; // 发送客户端失败

    const ERR_LOGIC = 9401; // 服务端逻辑错误

    const ERR_TARS_CANNOT_CONVERT = 9501;
    const ERR_TARS_OUTOF_RANGE = 9502;
    const ERR_TARS_MALLOC_FAILED = 9503;
    const ERR_TARS_CLASS_UNINIT = 9504;
    const ERR_TARS_REQUIRED_FIELD_LOST = 9505;
    const ERR_TARS_DATA_FORMAT_ERROR = 9506;
    const ERR_TARS_TYPE_INVALID = 9507;
    const ERR_TARS_CLASS_MISMATCH = 9508;
    const ERR_TARS_WRONG_PARAMS = 9509;
    const ERR_TARS_STATIC_FIELDS_PARAM_LOST = 9510;
    const ERR_TARS_ARRAY_RETRIEVE = 9511;
    const ERR_TARS_READ_MAP_ERROR = 9512;
    const ERR_TARS_SET_CONTEXT_ERROR = 9513;
    const ERR_TARS_SET_STATUS_ERROR = 9514;
    const ERR_TARS_ENCODE_BUF_ERROR = 9515;
    const ERR_TARS_WRITE_IVERSION_ERROR = 9516;
    const ERR_TARS_WRITE_CPACKETTYPE_ERROR = 9517;
    const ERR_TARS_WRITE_IMESSAGETYPE_ERROR = 9518;
    const ERR_TARS_WRITE_IREQUESTID_ERROR = 9519;
    const ERR_TARS_WRITE_SSERVANTNAME_ERROR = 9520;
    const ERR_TARS_WRITE_SFUNCNAME_ERROR = 9521;
    const ERR_TARS_WRITE_SBUFFER_ERROR = 9522;
    const ERR_TARS_WRITE_ITIMEOUT_ERROR = 9523;
    const ERR_TARS_WRITE_CONTEXT_ERROR = 9524;
    const ERR_TARS_WRITE_STATUS_ERROR = 9525;
    const ERR_TARS_STRUCT_COMPLICATE_NOT_DEFINE = 9526;
    const ERR_TARS_VECOTR_OR_MAP_EXT_PARAM_LOST = 9527;
    const ERR_TARS_STATIC_NAME_NOT_STRING_ERROR = 9528;
    const ERR_TARS_STATIC_REQUIRED_NOT_BOOL_ERROR = 9529;
    const ERR_TARS_STATIC_TYPE_NOT_LONG_ERROR = 9530;
    const ERR_TARS_UNKNOWN = 9530;
    
    const ERR_CLIENT_UNKNOWN = 9601;

    const ERR_UNKNOWN = 9901; // 未知错误


    protected $errMsg = [
        0 => 'success',

        9001 => '错误的包头',
        9002 => '请求包体长度超过允许的范围',
        9003 => '服务器繁忙',

        9204 => '解包失败',
        9205 => '参数错误',
        9206 => '函数不存在',
        9207 => '执行错误',
        9208 => '访问被拒绝，客户端主机未被授权',
        9209 => '用户名密码错误',
        9210 => '不支持的打包格式',
        9211 => 'tars包数据不合法',

        9301 => '发送客户端失败',

        9401 => '服务端逻辑错误',

        9501 => 'tars错误，无法打包',
        9502 => 'tars错误，超出范围',
        9503 => 'tars错误，申请内存失败',
        9504 => 'tars错误，类未初始化',
        9505 => 'tars错误，required字段缺失',
        9506 => 'tars错误，数据格式错误',
        9507 => 'tars错误，类型错误',
        9508 => 'tars错误，类不匹配',
        9509 => 'tars错误，参数错误',
        9510 => 'tars错误，静态字段缺失',
        9511 => 'tars错误，数组中检索',
        9512 => 'tars错误，读取map失败',
        9513 => 'tars错误，设置context字段错误',
        9514 => 'tars错误，设置status字段错误',
        9515 => 'tars错误，打包数据失败',
        9516 => 'tars错误，写入IVERSION信息错误',
        9517 => 'tars错误，写入CPACKETTYPE字段错误',
        9518 => 'tars错误，写入IMESSAGETYPE字段错误',
        9519 => 'tars错误，写入IREQUESTID字段错误',
        9520 => 'tars错误，写入SSERVANTNAME字段错误',
        9521 => 'tars错误，写入SFUNCNAME字段错误',
        9522 => 'tars错误，写入SBUFFER字段错误',
        9523 => 'tars错误，写入ITIMEOUT字段错误',
        9524 => 'tars错误，写入CONTEXT字段错误',
        9525 => 'tars错误，写入STATUS字段错误',
        9526 => 'tars错误，struct不匹配',
        9527 => 'tars错误，vector或者map扩展字段缺失',
        9528 => 'tars错误，name不是string类型',
        9529 => 'tars错误，required不是bool类型',
        9530 => 'tars错误，type不是long类型',
        9531 => 'tars错误，未知错误',

        9601 => '客户端未知错误',

        9901 => '未知错误',
    ];

    /**
     * @var array
     */
    protected $context = [];

    /**
     * 抛异常时一般可以仅填写已提供的错误码，如果错误码不是已提供的，会自动转为ERR_UNKNOWN
     * 异常信息如果没有提供，会使用异常字段的错误码描述信息
     * 
     * @param int $code
     * @param array $context 上下文信息
     * @param string $message
     */
    public function __construct(int $code, array $context = [], string $message = null)
    {
        if (!isset($this->errMsg[$code])) {
           $code = self::ERR_UNKNOWN; 
        }

        if (is_null($message)) {
            $message = $this->errMsg[$code];
        }

        $this->context = $context;
        
        parent::__construct($message, $code);
    }

    /**
     * 获取异常上下文信息
     * 
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }
}
