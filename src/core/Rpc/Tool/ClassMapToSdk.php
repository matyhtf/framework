<?php

namespace SPF\Rpc\Tool;

use ReflectionClass;
use ReflectionMethod;
use SPF\Rpc\Config;
use SPF\Rpc\Tool\Tars2php\Utils;

class ClassMapToSdk
{
    /**
     * 类中use的类名列表
     *
     * @var array
     */
    protected $classUses = [];

    /**
     * sdk根路径
     *
     * @var string
     */
    protected $rootDir;

    /**
     * sdk源码路径
     *
     * @var string
     */
    protected $srcDir;

    /**
     * sdk实现代码路径
     *
     * @var string
     */
    protected $sdkDir;

    /**
     * 服务端实现代码路径
     *
     * @var string
     */
    protected $libImplDir;

    /**
     * sdk命名空间前缀
     *
     * @var string
     */
    protected $sdkNs;

    public function __construct()
    {
        $this->rootDir = Config::$rootPath . '/' . Config::getOrFailed('app.tars2sdk.rootDir');
        $this->srcDir = $this->rootDir . '/' . Config::getOrFailed('app.tars2sdk.srcDir');
        $this->sdkDir = $this->srcDir . '/' . Config::getOrFailed('app.tars2sdk.sdkDir');
        $this->libImplDir = Config::$rootPath . '/' . Config::getOrFailed('app.tars.dstPath') . '/' . Config::getOrFailed('app.tars.implDir');
        $this->sdkNs = Config::getOrFailed('app.tars2sdk.sdkNs');
    }

    /**
     * @param array $classMap
     * @param bool $withSdkTools
     * @param bool $withTarsFiles
     */
    public function handle($classMap, $withSdkTools = true, $withTarsFiles = false)
    {
        foreach ($classMap as $class => $methods) {
            $this->genClass($class, $methods);
        }

        $this->copyStruct();

        if ($withTarsFiles) {
            $this->copyTarsFile();
        }

        if ($withSdkTools) {
            $this->copySdkTools();
        }
    }

    /**
     * 生成类文件
     *
     * @param string $class
     * @param string $methods
     */
    protected function genClass($class, $methods)
    {
        $this->classUses = Config::get('app.tars2sdk.appendClassUses', []);
        ;

        list($filename, $source) = $this->getSavePath($class);
        $ns = $this->getSaveClassNs($class);
        $className = $this->getSimpleClassName($class);

        $codeBody = '';
        foreach ($methods as $method => $param) {
            $codeBody .= $this->genMethod($class, $method, $param);
        }

        // 代码header
        $code = "<?php" . Utils::lineFeed(2);
        $code .= "namespace {$ns};" . Utils::lineFeed(2);
        
        // 命名空间去重、去除\前缀
        $this->classUses = array_unique($this->classUses);
        foreach ($this->classUses as &$use) {
            $use = $this->removeNsPrefix($use);
            $code .= "use {$use};" . Utils::lineFeed(1);
        }
        unset($use);

        $code .= Utils::lineFeed(1);
        $code .= "class {$className}" . Utils::lineFeed(1) . "{" . Utils::lineFeed(1);
        $code .= $codeBody;

        $code .= "}" . Utils::lineFeed(1);

        // 统一替换struct命名空间为sdk中命名空间
        $code = $this->replaceStructNs($code);

        Utils::log("<info>源文件 <comment>{$source}</comment> 生成sdk文件 <comment>{$filename}</comment></info>", 'raw');

        file_put_contents($filename, $code);
    }

    /**
     * 生成方法
     *
     * @param string $class
     * @param string $method
     * @param array $param
     *
     * @return string
     */
    protected function genMethod($class, $method, $param)
    {
        $refMethod = new ReflectionMethod($class, $method);
        $methodDoc = $refMethod->getDocComment();
        $code = Utils::indent(1) . $methodDoc . Utils::lineFeed(1);
        $code .= Utils::indent(1) . "public function {$method}(";

        // 对参数先后顺序排序
        usort($param['params'], function ($a, $b) {
            return $a['index'] - $b['index'] > 0 ? 1 : -1;
        });

        // 方法参数拼接
        foreach ($param['params'] as $funcParam) {
            // 参数修饰符
            $flagAnd = $funcParam['ref'] ? '&' : '';
            if ($this->isStruct($funcParam['type'])) {
                $code .= $this->getSimpleClassName($funcParam['proto']) . " ";
            } else {
                if ($type = $this->buildInType($funcParam['type'])) {
                    $code .= "{$type} ";
                }
            }

            $code .= "{$flagAnd}\${$funcParam['name']}, ";
        }
        $code = rtrim($code, ", ");

        $code .= ")" . Utils::lineFeed(1) .
            Utils::indent(1) . "{" . Utils::lineFeed(1);
        $code .= Utils::indent(2) . '$encodeBufs = [];' . Utils::lineFeed(2);
        
        // 对参数的值使用tars的API进行打包
        if (!empty($param['params'])) {
            foreach ($param['params'] as $funcParam) {
                $type = $funcParam['type'];
                $name = $funcParam['name'];
                $packMethod = Utils::getPackMethods($type);
                $index = $funcParam['index'] + 1;
                $funcParam['proto'] = $this->transferProto($funcParam['proto']);

                if (Utils::isVector($type)) {
                    $code .= Utils::indent(2) . "\${$name}Vec = new {$funcParam['proto']};" . Utils::lineFeed(1) .
                        Utils::indent(2) . "foreach (\${$name} as \$item{$name}) {" . Utils::lineFeed(1) .
                        Utils::indent(3) . "\${$name}Vec->pushBack(\$item{$name});" . Utils::lineFeed(1) .
                        Utils::indent(2) . "}" . Utils::lineFeed(1) .
                        Utils::indent(2) . "\$encodeBufs[] = TUPAPIWrapper::{$packMethod}(\"{$name}\", {$index}, \${$name}Vec);" . Utils::lineFeed(1);
                } elseif (Utils::isMap($type)) {
                    $code .= Utils::indent(2) . "\${$name}Map = new {$funcParam['proto']};" . Utils::lineFeed(1) .
                        Utils::indent(2) . "foreach (\${$name} as \$key{$name} => \$val{$name}) {" . Utils::lineFeed(1) .
                        Utils::indent(3) . "\${$name}Map->pushBack([\$key{$name} => \$val{$name}]);" . Utils::lineFeed(1) .
                        Utils::indent(2) . "}" . Utils::lineFeed(1) .
                        Utils::indent(2) . "\$encodeBufs[] = TUPAPIWrapper::{$packMethod}(\"{$name}\", {$index}, \${$name}Map);" . Utils::lineFeed(1);
                } elseif ($this->isStruct($funcParam['proto'])) {
                    $this->classUses[] = $funcParam['proto'];
                    $code .= Utils::indent(2) . "\$encodeBufs[] = TUPAPIWrapper::{$packMethod}(\"{$name}\", {$index}, \${$name});" . Utils::lineFeed(1);
                } else {
                    $code .= Utils::indent(2) . "\$encodeBufs[] = TUPAPIWrapper::{$packMethod}(\"{$name}\", {$index}, \${$name});" . Utils::lineFeed(1);
                }
            }

            $code .= Utils::lineFeed(1);
        }

        $code .= Utils::indent(2) . "\$response = RpcClient::staticCall(__CLASS__, __FUNCTION__, \$encodeBufs);" . Utils::lineFeed(2);

        // 对于取地址符的参数进行特殊处理
        $returnLineFeed = false;
        foreach ($param['params'] as $funcParam) {
            if (!$funcParam['ref']) {
                continue;
            }

            $returnLineFeed = true;

            $type = $funcParam['type'];
            $name = $funcParam['name'];
            $unpackMethod = Utils::getUnpackMethods($type);
            $index = $funcParam['index'] + 1;
            $funcParam['proto'] = $this->transferProto($funcParam['proto']);

            if (Utils::isVector($type) || Utils::isMap($type)) {
                $code .= Utils::indent(2) . "\${$name} = TUPAPIWrapper::{$unpackMethod}(\"{$name}\", {$index}, new {$funcParam['proto']}, \$response, true);" . Utils::lineFeed(1);
            } elseif ($this->isStruct($funcParam['proto'])) {
                $this->classUses[] = $funcParam['proto'];
                $code .= Utils::indent(2) . "\${$name} = TUPAPIWrapper::{$unpackMethod}(\"{$name}\", {$index}, \$response, true);" . Utils::lineFeed(1);
            } else {
                $code .= Utils::indent(2) . "\${$name} = TUPAPIWrapper::{$unpackMethod}(\"{$name}\", {$index}, \$response, true);" . Utils::lineFeed(1);
            }
        }

        if ($returnLineFeed) {
            $code .= Utils::lineFeed(1);
        }

        // 返回值处理
        $return = $param['return'];
        if ($return['type'] != 'void') {
            $returnUnpack = Utils::getUnpackMethods($return['type']);
            $return['proto'] = $this->transferProto($return['proto']);

            if (Utils::isVector($return['type'])) {
                $code .= Utils::indent(2) . "\$retVec = new {$return['proto']};" . Utils::lineFeed(2) .
                    Utils::indent(2) . "return TUPAPIWrapper::{$returnUnpack}(\"\", 0, \$retVec, \$response, true);" . Utils::lineFeed(1);
            } elseif (Utils::isMap($return['type'])) {
                $code .= Utils::indent(2) . "\$retMap = new {$return['proto']};" . Utils::lineFeed(2) .
                    Utils::indent(2) . "return TUPAPIWrapper::{$returnUnpack}(\"\", 0, \$retMap, \$response, true);" . Utils::lineFeed(1);
            } elseif ($this->isStruct($return['type'])) {
                $code .= Utils::indent(2) . "\$retStruct = new {$return['proto']};" . Utils::lineFeed(2) .
                    Utils::indent(2) . "return TUPAPIWrapper::{$returnUnpack}(\"\", 0, \$retStruct, \$response, true);" . Utils::lineFeed(1);
            } else {
                $code .= Utils::indent(2) . "return TUPAPIWrapper::{$returnUnpack}(\"\", 0, \$response, true);" . Utils::lineFeed(1);
            }
        }

        $code .= Utils::indent(1) . "}" . Utils::lineFeed(2);

        return $code;
    }

    /**
     * 根据类名获取简单类名
     *
     * @param string $class
     *
     * @return string
     */
    protected function getSimpleClassName($class)
    {
        $parts = explode('\\', $class);

        return $parts[count($parts) - 1];
    }

    /**
     * 内建类型转换
     *
     * @param string $type
     *
     * @return bool|string
     */
    protected function buildInType($type)
    {
        $map = [
            'bool' => 'bool',
            'double' => 'double',
            'float' => 'float',
            'string' => 'string',
            'short' => 'int',
            'uint8' => 'int',
            'int8' => 'int',
            'uint16' => 'int',
            'int16' => 'int',
            'int32' => 'int',
            'uint32' => 'int',
            'int64' => 'int',
        ];
        
        return $map[$type] ?? false;
    }

    /**
     * 判断是否是结构体
     *
     * @return bool
     */
    protected function isStruct($type)
    {
        return is_subclass_of($type, \TARS_Struct::class, true) || strtolower($type) === 'struct';
    }

    /**
     * 转换proto字段为可实例化对象
     *
     * @param string $proto
     *
     * @return string
     */
    protected function transferProto($proto)
    {
        if (preg_match('/^(.*?)\(([^\)]+)\)(.*?)$/', $proto, $matches)) {
            $parts = [];
            if (isset($matches[2])) {
                $parts = explode(',', $matches[2]);
                foreach ($parts as $idx => $param) {
                    if ($this->isStruct($param)) {
                        $this->classUses[] = $param;
                        $parts[$idx] = 'new ' . $this->getSimpleClassName($param) . '()';
                    }
                }
            }
            
            $proto = $matches[1] . '(' . implode(', ', $parts) . ')' . $matches[3];
        }

        return $proto;
    }

    /**
     * 根据类名计算保存路径
     *
     * @param string $class
     *
     * @return array
     */
    protected function getSavePath($class)
    {
        $refClass = new ReflectionClass($class);
        $source = $refClass->getFileName();
        
        $filename = $this->getRelativePath($this->libImplDir, $source);

        $path = $this->sdkDir . '/' .$filename;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        return [$path, $source];
    }

    /**
     * 获取相对路径
     *
     * @param string $referPath 参考路径
     * @param string $absolutePath 绝对路径
     *
     * @return string
     */
    protected function getRelativePath($referPath, $absolutePath)
    {
        return substr($absolutePath, strlen($referPath) + 1);
    }

    /**
     * 获取相对命名空间
     *
     * @param string $referNs 参考命名空间
     * @param string $absoluteNs 完整命名空间
     *
     * @return string
     */
    protected function getRelativeNs($referNs, $absoluteNs)
    {
        return substr($absoluteNs, strlen($referNs));
    }

    protected function getImplNs()
    {
        return Config::get('app.namespacePrefix') . '\\' . Config::get('app.tars.implNs');
    }

    protected function getStructNs()
    {
        return Config::get('app.namespacePrefix') . '\\' . Config::get('app.tars.structNs');
    }

    /**
     * 获取保存类的命名空间
     *
     * @param string $class
     *
     * @return string
     */
    protected function getSaveClassNs($class)
    {
        $ns = $this->sdkNs . '\\' . Config::get('app.tars2sdk.sdkDir') . $this->getRelativeNs($this->getImplNs(), $class);

        $parts = explode('\\', $ns);
        array_pop($parts);

        return implode('\\', $parts);
    }

    protected function getSaveStructNs($struct)
    {
        return $this->sdkNs . '\\Structs' . $this->getRelativeNs($this->getStructNs(), $struct);
    }

    protected function replaceStructNs($code)
    {
        $search = [];
        $replace = [];
        foreach ($this->classUses as $class) {
            if ($this->isStruct($class)) {
                $search[] = $class;
                $replace[] = $this->getSaveStructNs($class);
            }
        }

        return str_replace($search, $replace, $code);
    }

    /**
     * 移除命名空间前缀
     *
     * @param string $ns
     *
     * @return string
     */
    protected function removeNsPrefix($ns)
    {
        if (strpos($ns, '\\') === 0) {
            $ns = substr($ns, 1);
        }

        return $ns;
    }

    /**
     * 复制struct类
     */
    protected function copyStruct()
    {
        $structSaveDir = $this->srcDir . '/Structs';
        if (!is_dir($structSaveDir)) {
            mkdir($structSaveDir, 0755, true);
        }

        $structPath = Config::$rootPath . '/' . Config::getOrFailed('app.tars.dstPath') . '/' . Config::getOrFailed('app.tars.structDir');
        foreach (Helper::recurseReadFolder($structPath) as $file) {
            $code = file_get_contents($file);

            $code = $this->filterCopyStructContent($code);

            $savePath = $structSaveDir . '/' . $file->getFilename();

            Utils::log("<info>copy struct <comment>{$file}</comment> 至 <comment>{$savePath}</comment></info>", 'raw');

            file_put_contents($savePath, $code);
        }
    }

    /**
     * 替换struct类中的命名空间为sdk命名空间
     *
     * @param string $content
     *
     * @return string
     */
    protected function filterCopyStructContent($content)
    {
        $content = str_replace(
            'namespace ' . $this->getStructNs() . ';',
            'namespace ' . $this->sdkNs . '\\Structs;',
            $content
        );

        return $content;
    }

    /**
     * 复制tars文件
     */
    protected function copyTarsFile()
    {
        $tarsSaveDir = $this->rootDir . '/tars';
        if (!is_dir($tarsSaveDir)) {
            mkdir($tarsSaveDir, 0755, true);
        }

        $tarsPath = Config::$rootPath . '/' . Config::getOrFailed('app.tars.dstPath') . '/' . Config::getOrFailed('app.tars.tarsDir');
        foreach (Helper::recurseReadFolder($tarsPath) as $file) {
            $savePath = $tarsSaveDir . '/' . $file->getFilename();

            Utils::log("<info>copy tars <comment>{$file}</comment> 至 <comment>{$savePath}</comment></info>", 'raw');
            copy($file, $savePath);
        }
    }

    /**
     * 复制sdktools文件
     */
    protected function copySdkTools()
    {
        foreach (Config::get('app.tars2sdk.sdkTools') as $source => $target) {
            // 使用相对路径时，自动补充$source的路径前缀为Config::$rootPath / sdktools
            if (strpos($source, '/') !== 0) {
                $source = Config::$rootPath . '/sdktools/' . $source;
            }
            // 使用相对路径时，自动补充$target的路径前缀为$this->rootDir
            if (strpos($target, '/') !== 0) {
                $target = $this->rootDir . '/' . $target;
            }

            Utils::log("<info>copy sdktools <comment>{$source}</comment> 至 <comment>{$target}</comment></info>", 'raw');
            copy($source, $target);
        }
    }
}
