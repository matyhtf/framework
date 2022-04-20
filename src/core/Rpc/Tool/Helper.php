<?php

namespace SPF\Rpc\Tool;

use SPF\Rpc\Config;
use SPF\Rpc\RpcException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Helper
{   
    /**
     * @param string $bufferFuncName
     * 
     * @return array
     */
    public static function parserFuncName($bufferFuncName)
    {
        // ns1.ns2.class@func 格式
        list($package, $func) = explode('@', $bufferFuncName);
        $class = str_replace('.', '\\', $package);

        $nsPrefix = Config::get('app.namespacePrefix', '');
        $nsImpl = Config::get('app.tars.implNs', 'Impl');
        $fullClass = "{$nsPrefix}\\{$nsImpl}\\{$class}";
        
        $map = ReflectionClassMap::getMap();

        if (!isset($map[$fullClass])) {
            throw new RpcException(RpcException::ERR_NOFUNC, ['class' => $class, 'fullClass' => $fullClass]);
        }
        if (!isset($map[$fullClass][$func])) {
            throw new RpcException(RpcException::ERR_NOFUNC, ['class' => $class, 'fullClass' => $fullClass, 'function' => $func]);
        }

        return [
            'class' => $fullClass,
            'function' => $func,
            'params' => $map[$fullClass][$func],
        ];
    }

    /**
     * 设置进程名称
     * 
     * @param stirng $name
     */
    public static function setProcessName($name)
    {
        if (function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        } elseif (function_exists('cli_set_process_title')) {
            cli_set_process_title($name);
        }
    }

    /**
     * 递归读取文件夹
     * 
     * @param string $folder
     * 
     * @return \RecursiveIteratorIterator
     */
    public static function recurseReadFolder($folder)
    {
        $iterator = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);

        return new RecursiveIteratorIterator($iterator);
    }
}
