<?php

namespace SPF\Rpc\Tool\Tars2php;

use SPF\Exception\Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SPF\Rpc\Config;

class FileConverter
{
    public $preStructs = [];
    public $preEnums = [];
    public $preNamespaceStructs = [];
    public $preNamespaceEnums = [];

    public $config = [
        // struct结构体子目录
        'structDir' => 'Structs',
        // struct结构体命名空间
        'structNs' => 'Structs',
        // tars源文件子目录
        'tarsDir' => 'tars',
        // interface子目录
        'interfaceDir' => 'Interfaces',
        // interface命名空间
        'interfaceNs' => 'Interfaces',
        // client子目录
        'clientDir' => 'Clients',
        // client命名空间
        'clientNs' => 'Clients',
    ];

    /**
     * 项目根目录
     * 
     * @var string
     */
    public $rootPath;

    /**
     * 生成文件输出目录
     * 
     * @var string
     */
    public $outputDir;
    
    /**
     * 命名空间前缀
     * 
     * @var string
     */
    protected $nsPrefix = '';

    public function __construct(array $config, $rootPath = null)
    {
        $this->rootPath = $rootPath ?: Config::$rootPath;

        $this->config = array_merge($this->config, $config);

        $this->validateConfig();

        $this->outputDir = $this->resolvePath($this->config['dstPath']);

        $this->nsPrefix = strrpos($this->config['nsPrefix'], '\\\\') === 0 
            ? mb_substr($this->config['nsPrefix'], 0, -2) : $this->config['nsPrefix'];

        $this->initDir();
    }

    /**
     * 验证配置参数是否正确
     */
    protected function validateConfig()
    {
        // 验证基本参数
        if (
            empty($this->config['structDir']) || empty($this->config['structNs']) || empty($this->config['tarsDir'])
            || empty($this->config['interfaceDir']) || empty($this->config['interfaceNs'])
        ) {
            throw new Exception('配置参数错误');
        }

        // 验证命名空间
        if (
            empty($this->config['nsPrefix'])
            || preg_match('/^[a-zA-Z]/', $this->config['nsPrefix']) === 0
            || preg_match('/^[a-zA-Z0-9\\\\]+$/', $this->config['nsPrefix']) === 0
        ) {
            throw new Exception('配置 nsPrefix 格式不合法,必须为有效命名空间');
        }

        // 验证tars文件夹/文件列表
        if (is_string($this->config['tarsFiles'])) {
            // 目录类型
            if (!is_dir($this->resolvePath($this->config['tarsFiles']))) {
                throw new Exception('配置 tarsFiles 指定tars文件夹不存在');
            }
        } elseif (is_array($this->config['tarsFiles'])) {
            // 文件列表类型
            foreach($this->config['tarsFiles'] as $tarsFile) {
                if (!($file = $this->resolvePath($tarsFile)) || !is_file($file)) {
                    throw new Exception('配置 tarsFiles 指定文件 ' . $file . '不存在');
                }
            }
        } else {
            throw new Exception('配置 tarsFiles 不合法,必须为字符串(目录)或者数组(tars文件列表)');
        }

        // 验证输出文件夹
        if (!is_dir($this->resolvePath($this->config['dstPath']))) {
            throw new Exception('配置 dstPath 输出文件夹不存在');
        }
    }

    /**
     * @param string $path
     * 
     * @return string|bool
     */
    protected function resolvePath($path, $rootPath = null)
    {
        $rootPath = $rootPath ?: $this->rootPath;
        
        if (strpos($path, '/') === 0) {
            // 绝对路径
            $realPath = realpath($path);
        } else {
            // 相对路径
            $realPath = realpath($rootPath . '/' . $path);
        }

        if ($realPath === false) {
            return $realPath;
        }

        // 去掉目录后面的 / 分隔符
        if (strrpos($realPath, '/') === 0) {
            return mb_substr($realPath, 0, -1);
        }

        return $realPath;
    }

    /**
     * 初始化目录.
     */
    protected function initDir()
    {
        $structDir = $this->outputDir . DIRECTORY_SEPARATOR . $this->config['structDir'];
        if (!is_dir($structDir)) {
            mkdir($structDir, 0755, true);
        }

        $interfaceDir = $this->outputDir . DIRECTORY_SEPARATOR . $this->config['interfaceDir'];
        if (!is_dir($interfaceDir)) {
            mkdir($interfaceDir, 0755, true);
        }

        $tarsDir = $this->outputDir . DIRECTORY_SEPARATOR . $this->config['tarsDir'];
        if (!is_dir($tarsDir)) {
            mkdir($tarsDir, 0755, true);
        }
    }

    public function moduleScanRecursive()
    {
        if (is_string($this->config['tarsFiles'])) {
            // 目录
            $this->moduleScanByDir($this->config['tarsFiles']);
        } else {
            // 文件列表
            $this->moduleScanByFiles($this->config['tarsFiles']);
        }
    }

    /**
     * @param string $path
     */
    protected function moduleScanByDir($path)
    {
        $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        foreach($files as $file) {
            $this->moduleScan((string) $file);
        }
    }

    /**
     * @param array $files
     */
    protected function moduleScanByFiles($files)
    {
        foreach($files as $file) {
            $file = $this->resolvePath($file);
            $this->moduleScan($file);
        }
    }

    /**
     * @param string $tarsFile
     */
    protected function moduleScan($tarsFile)
    {
        $tarsDstPath = $this->outputDir . '/' . $this->config['tarsDir'] . '/' . basename($tarsFile);
        Utils::log("<info>复制tars文件： <comment>[".basename($tarsFile)."]</comment> 到 <comment>[{$tarsDstPath}]</comment></info>", 'raw');
        copy($tarsFile, $tarsDstPath);

        $fp = fopen($tarsFile, 'r');
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有module
            $moduleFlag = strpos($line, 'module');
            if ($moduleFlag !== false) {
                $name = Utils::pregMatchByName('module', $line);
                $currentModule = $name;
            }

            // 判断是否有include
            $includeFlag = strpos($line, '#include');
            if ($includeFlag !== false) {
                // 找出tars对应的文件名
                $tokens = preg_split('/#include/', $line);
                $includeFile = trim($tokens[1], "\" \r\n;");

                $realIncludeFile = $this->resolvePath($includeFile, dirname($tarsFile));
                if (!$realIncludeFile) {
                    throw new Exception("include文件 {$includeFile} 路径不合法: {$realIncludeFile}");
                }

                $this->moduleScan($realIncludeFile);
            }

            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*' || trim($line) === '{') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line, 'enum');
            if ($enumFlag !== false) {
                $name = Utils::pregMatchByName('enum', $line);
                if (!empty($name)) {
                    $this->preEnums[] = $name;

                    // 增加命名空间以备不时之需
                    if (!empty($currentModule)) {
                        $this->preNamespaceEnums[] = $currentModule . '::' . $name;
                    }

                    while (($lastChar = fgetc($fp)) != '}') {
                        continue;
                    }
                }
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                if (!empty($name)) {
                    $this->preStructs[] = $name;
                    // 增加命名空间以备不时之需
                    if (!empty($currentModule)) {
                        $this->preNamespaceStructs[] = $currentModule . '::' . $name;
                    }
                }
            }
        }
        fclose($fp);
    }

    public function moduleParserRecursive()
    {
        if (is_string($this->config['tarsFiles'])) {
            // 目录
            $this->moduleParserByDir($this->config['tarsFiles']);
        } else {
            // 文件列表
            $this->moduleParserByFiles($this->config['tarsFiles']);
        }
    }

    /**
     * @param string $path
     */
    protected function moduleParserByDir($path)
    {
        $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);
        foreach ($files as $file) {
            $this->moduleParse($file);
        }
    }

    /**
     * @param array $files
     */
    protected function moduleParserByFiles($files)
    {
        foreach ($files as $file) {
            $file = $this->resolvePath($file);
            $this->moduleParse($file);
        }
    }

    public function moduleParse($tarsFile)
    {
        $fp = fopen($tarsFile, 'r');
        while (($line = fgets($fp, 1024)) !== false) {

            // 判断是否有include
            $includeFlag = strpos($line, '#include');
            if ($includeFlag !== false) {
                // 找出tars对应的文件名
                $tokens = preg_split('/#include/', $line);
                $includeFile = trim($tokens[1], "\" \r\n;");

                $realIncludeFile = $this->resolvePath($includeFile, dirname($tarsFile));
                if (!$realIncludeFile) {
                    throw new Exception("include文件 {$includeFile} 路径不合法: {$realIncludeFile}");
                }

                $this->moduleParse($realIncludeFile);
            }

            // 如果空行，或者是注释，就直接略过
            if (!$line || trim($line) == '' || trim($line)[0] === '/' || trim($line)[0] === '*') {
                continue;
            }

            // 正则匹配,发现是在enum中
            $enumFlag = strpos($line, 'enum');
            if ($enumFlag !== false) {
                // 处理第一行,正则匹配出classname
                $enumTokens = preg_split('/enum/', $line);

                $enumName = $enumTokens[1];
                $enumName = trim($enumName, " \r\0\x0B\t\n{");

                // 判断是否是合法的structName
                preg_match('/[a-zA-Z][0-9a-zA-Z]/', $enumName, $matches);
                if (empty($matches)) {
                    Utils::abnormalExit('error', 'Enum名称有误');
                }

                $this->preEnums[] = $enumName;
                while (($lastChar = fgetc($fp)) != '}') {
                    continue;
                }
            }

            // 正则匹配,发现是在consts中
            $constFlag = strpos($line, 'const');
            if ($constFlag !== false) {
                // 直接进行正则匹配
                Utils::abnormalExit('warning', 'const is not supported, please make sure you deal with them yourself in this version!');
            }

            // 正则匹配，发现是在结构体中
            $structFlag = strpos($line, 'struct');
            // 一旦发现了struct，那么持续读到结束为止
            if ($structFlag !== false) {
                $name = Utils::pregMatchByName('struct', $line);

                $structParser = new StructParser(
                    $fp,
                    $name,
                    $this->nsPrefix,
                    $this->config,
                    $this->preStructs,
                    $this->preEnums,
                    $this->preNamespaceEnums,
                    $this->preNamespaceStructs
                );
                $structClassStr = $structParser->parse();

                $outputDir = $this->outputDir . '/' . $this->config['structDir'] . '/' . $name . '.php';
                file_put_contents($outputDir, $structClassStr);

                Utils::log("<info>生成struct：<comment>[{$outputDir}]</comment></info>", 'raw');
            }

            // 正则匹配，发现是在interface中
            $interfaceFlag = strpos(strtolower($line), 'interface');
            // 一旦发现了struct，那么持续读到结束为止
            // TODO
            if ($interfaceFlag !== false) {
                $name = Utils::pregMatchByName('interface', $line);
                $interfaceName = $name . 'Servant';

                if (true) {
                    // Server端
                    $servantParser = new ServantParser(
                        $fp,
                        $interfaceName,
                        $this->nsPrefix,
                        $this->config,
                        $this->preStructs,
                        $this->preEnums,
                        $this->preNamespaceEnums,
                        $this->preNamespaceStructs
                    );
                    $servant = $servantParser->parse();

                    $outputDir = $this->outputDir . '/' . $this->config['interfaceDir'] . '/' . $interfaceName . '.php';
                    file_put_contents($outputDir, $servant);

                    Utils::log("<info>生成interface：<comment>[{$outputDir}]</comment></info>", 'raw');
                } else {
                    // Client端
                    // TODO
                    $ClientParser = new ClientParser(
                        $fp,
                        $line,
                        $this->namespaceName,
                        $this->moduleName,
                        $interfaceName,
                        $this->preStructs,
                        $this->preEnums,
                        $this->servantName,
                        $this->preNamespaceEnums,
                        $this->preNamespaceStructs
                    );
                    $interfaces = $ClientParser->parse();

                    // 需要区分同步和异步的两种方式
                    $outputDir = $this->outputDir . '/' . $this->config['clientDir'] . '/' . $interfaceName . '.php';
                    file_put_contents($outputDir, $interfaces['syn']);
                }
            }
        }
    }
}
