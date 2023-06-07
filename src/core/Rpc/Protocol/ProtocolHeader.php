<?php

namespace SPF\Rpc\Protocol;

class ProtocolHeader
{
    const HEADER_SIZE = 32;
    // length: body数据长度，使用mb_strlen计算
    // formatter: body打包格式，tars|protobuf|serialize|json，会转换为enum类型，此处为ID
    // request_id: 请求ID
    // errno: 错误码
    // uid: 用户ID
    // server_version: 当前服务版本，由服务提供方定义，格式为 六位整数，前两位为主版本，中间两位为子版本，最后两位为修正版本：
    //      例如 1.13.5，则标记为 011305，省略前面的0，变成 11305
    // sdk_version: 当前RPC SDK版本，由RPC基础组件提供方定义
    // reserve: 保留字段
    const HEADER_STRUCT = "Nlength/Nformatter/Nrequest_id/Nerrno/Nuid/Nserver_version/Nsdk_version/Nreserve";
    const HEADER_PACK = "NNNNNNNN"; // 4*8=32

    /**
     * @param string $response
     * @param int $length
     * @param int $formatter
     * @param int $errno
     * @param int $requestId
     * @param int $uid
     * @param int $serVersion
     * @param int $sdkVersion
     * @param int $reserve
     *
     * @return string
     */
    public static function encode(
        $response,
        $length,
        $formatter,
        $errno = 0,
        $requestId = 0,
        $uid = 0,
        $serVersion = 0,
        $sdkVersion = 0,
        $reserve = 0
    ) {
        return pack(
            self::HEADER_PACK,
            $length,
            $formatter,
            $requestId,
            $errno,
            $uid,
            $serVersion, // TODO 可能因为多进程，导致配置取不到
            $sdkVersion,
            $reserve // 保留字段
        ) . $response;
    }

    /**
     * @param string $request
     *
     * @return array ['header' => header, 'body' => body]
     */
    public static function decode($request)
    {
        $header = unpack(self::HEADER_STRUCT, substr($request, 0, self::HEADER_SIZE));
        $body = substr($request, self::HEADER_SIZE);

        return compact('header', 'body');
    }
}
