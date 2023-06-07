<?php

namespace SPF\Generator;

use PhpParser\ParserFactory;
use PhpParser\Parser;
use PhpParser\Lexer;
use PhpParser\Node\Stmt;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use ReflectionClass;
use ReflectionParameter;
use ReflectionType;
use Throwable;
use SPF\Exception\LogicException;

class RpcTests
{
    /**
     * @var Parser
     */
    protected $parser = null;

    /**
     * Symfony console output instance.
     *
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Symfony console style instance.
     *
     * @var SymfonyStyle
     */
    protected $io = null;

    /**
     * Test cases source path.
     *
     * @var string
     */
    protected $sourcePath = null;

    /**
     * Test cases output path.
     *
     * @var string
     */
    protected $outputPath = null;

    /**
     * Test case template.
     *
     * @var string
     */
    protected static $template = null;

    /**
     * Process class`s count.
     *
     * @var integer
     */
    protected $handleClassCount = 0;

    /**
     * Process test case`s count.
     *
     * @var integer
     */
    protected $handleTestCaseCount = 0;

    /**
     * Repeat test case file process mode.
     *
     * @var string
     */
    protected $repeatFileMode = null;

    /**
     * The user last select choice.
     *
     * @var string
     */
    protected $repeatFileLastMode = null;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output = null, SymfonyStyle $io = null)
    {
        $this->output = $output;

        $this->io = $io;

        $this->initParser();
    }

    /**
     * Handle.
     *
     * @param string $src
     * @param string $output
     * @param string $mode
     */
    public function handle($src, $output, $mode)
    {
        $this->checkBootstrap();

        $this->sourcePath = $src;
        $this->outputPath = $output;
        $this->handleClassCount = 0;
        $this->handleTestCaseCount = 0;
        $this->repeatFileMode = $mode;
        $this->repeatFileLastMode = null;

        $this->writeln("<info>start generate test cases<info>");

        $this->recursiveHandle($src);

        $this->writeln("<info>processed class {$this->handleClassCount}, test cases {$this->handleTestCaseCount}<info>");
    }

    /**
     * Check on bootstrap.
     */
    protected function checkBootstrap()
    {
        if (is_null(static::getTemplate())) {
            throw new LogicException('The test case template is empty, please set the template by ' . __CLASS__ . '::setTemplate($template).');
        }
    }

    /**
     * Recursive handle generate opration.
     *
     * @param string $dirSrc
     */
    protected function recursiveHandle($dirSrc)
    {
        $dir = dir($dirSrc);
        while ($file = $dir->read()) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $path = "$dirSrc/$file";
            if (is_dir($path)) {
                $this->recursiveHandle($path);
                continue;
            }

            $code = file_get_contents($path);
            $stmts = $this->getParser()->parse($code);

            // recursive read stmts and generate test case template
            $namespace = null;
            $this->recursiveReadStmts($stmts, $namespace);

            $this->handleClassCount++;
        }
        $dir->close();
    }

    /**
     * @param array $stmts php-parser stmts array
     * @param string $namespace the code`s namespace
     */
    protected function recursiveReadStmts($stmts, &$namespace)
    {
        foreach ($stmts as $node) {
            // get the code file`s namesapce
            if ($node instanceof Stmt\Namespace_) {
                $namespace = (string) $node->name;
            }
            // get the class name and parse by reflection
            if ($node instanceof Stmt\Class_) {
                $className = (string) $node->name;
                $this->parseClassByReflection($className, $namespace);
                continue;
            }
            // recursive read stmts if there is any other class
            if (isset($node->stmts)) {
                $this->recursiveReadStmts($node->stmts, $namespace);
                continue;
            }
        }
    }

    /**
     * Get class full name by namespace and class simple name
     *
     * @param string $class
     * @param string $namesapce
     *
     * @return string
     */
    protected function getClassFullName($class, $namespace = null)
    {
        if (is_null($namespace)) {
            return $class;
        } else {
            return $namespace . '\\' . $class;
        }
    }

    /**
     * Using reflection parse class
     *
     * @param string $className
     * @param string $namespace
     */
    protected function parseClassByReflection($className, $namespace = null)
    {
        $classFullName = $this->getClassFullName($className, $namespace);
        $refClass = new ReflectionClass($classFullName);
        // if the class is not instantiable such as trait, abstract, interface, the construct function of the class
        // is private and the class is anonymous class, there will ignore the class and not be parsed.
        if (!$refClass->isInstantiable() || $refClass->isAnonymous()) {
            return;
        }

        // parse the class methods
        foreach ($refClass->getMethods() as $refMethod) {
            // if the method is not public, then continue.
            if (!$refMethod->isPublic()) {
                continue;
            }

            // parse the method params
            $params = [];
            foreach ($refMethod->getParameters() as $refParam) {
                $params[] = $this->parseMethodParam($refParam);
            }

            // the method return type
            $return = $refMethod->hasReturnType() ? $this->parseParamType($refMethod->getReturnType()) : null;
            $data = [
                'filename' => $refClass->getFileName(),
                'namespace' => $namespace,
                'class' => $className,
                'class_full_name' => $classFullName,
                'method' => $refMethod->getName(),
                'is_static' => $refMethod->isStatic(),
                'params' => $params,
                'return' => $return,
            ];

            // generate test case file.
            $this->generateTestCase($data);
        }
    }

    /**
     * Parse the method parameter.
     *
     * @param ReflectionParameter $refParam
     *
     * @return array
     */
    protected function parseMethodParam(ReflectionParameter $refParam)
    {
        $param = [
            'name' => $refParam->getName(),
            'pos' => $refParam->getPosition(),
            'type' => $this->parseParamType($refParam->getType()),
            'default' => null,
            'variadic' => $refParam->isVariadic(),
            'optional' => $refParam->isOptional(),
            'ref' => $refParam->isPassedByReference(),
            'nullable' => $refParam->allowsNull(),
        ];

        if ($refParam->isDefaultValueAvailable()) {
            $param['default'] = [
                'value' => $refParam->getDefaultValue(),
                'const' => $refParam->getDefaultValueConstantName(),
            ];
        }

        return $param;
    }

    /**
     * Parse parameter type
     *
     * @param ReflectionType $refType
     *
     * @return null|array
     */
    protected function parseParamType(ReflectionType $refType = null)
    {
        if (is_null($refType)) {
            return null;
        }

        return [
            'build_in' => $refType->isBuiltin(),
            'nullable' => $refType->allowsNull(),
            'name' => (string) $refType,
        ];
    }

    /**
     * @param array $data
     */
    protected function generateTestCase($data)
    {
        $paramsMock = $this->generateParamsMock($data['params']);

        $expect = $this->generateMethodReturn($data, $paramsMock);

        $callParams = $this->generateTestCaseCallParam($paramsMock);

        $callFunction = $this->generateCallFunction($data);

        $tplParams = [
            'namespace' => $data['namespace'],
            'class' => $data['class'],
            'class_full_name' => $data['class_full_name'],
            'method' => $data['method'],
            'title' => $this->generateTestCaseTest($data),
            'call_defined' => $callParams['call_defined'],
            'call_params' => $callParams['call_params'],
            'call_function' => $callFunction,
            'call' => $callParams['call_params'] ? $callFunction . ', ' . $callParams['call_params'] : $callFunction,
            'expect' => var_export($expect, true),
        ];

        $template = static::getTemplate();

        $code = $this->parseTemplate($template, $tplParams);

        $filename = $this->getSavePath($data);

        $mode = $this->repeatFileMode;
        // if there is repeat test case file, then select process mode
        if (file_exists($filename)) {
            // if there must select process mode and doesn`s have interact tool, then throw erros and default mode to skip
            if ($mode === 'confirm' && is_null($this->getIo())) {
                $this->writeln('<error>There doesn`s set Symfony Style instance IO, cannot interact and choice repeat mode, then default mode is skip.</error>');
                $mode = 'skip';
                $this->repeatFileMode = $mode;
            }

            if ($mode === 'confirm') {
                $mode = $this->getIo()->choice("测试用例 [$filename] 已存在，请选择处理方式：", [
                    'replace(r) - 新文件覆盖老文件',
                    'backup(b) - 备份老文件，然后覆盖新文件',
                    'skip(s) - 若老文件存在新文件自动跳过',
                ], $this->repeatFileLastMode);
                $this->repeatFileLastMode = $mode;
                $this->writeln("已选择：{$mode}");

                // the choice cannot use alias, so transfer the enum mode using the method below
                $mode = $this->transferChoiceMode($mode);
            }

            $this->saveTestCaseByMode("{$data['filename']}::{$data['method']}", $filename, $code, $mode);
        } else {
            file_put_contents($filename, $code);
            $this->handleTestCaseCount++;
            $this->writeln("<info>new create <comment>{$data['filename']}::{$data['method']}</comment> -> <comment>{$filename}</comment><info>");
        }
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function generateCallFunction($data)
    {
        return $data['is_static']
            ? "'{$data['class_full_name']}::{$data['method']}'"
            : "['{$data['class_full_name']}', '{$data['method']}']";
    }

    /**
     * @param array $data
     *
     * @return string
     */
    protected function generateTestCaseTest($data)
    {
        return $data['is_static']
            ? "{$data['class_full_name']}::{$data['method']}"
            : "{$data['class_full_name']}->{$data['method']}";
    }

    /**
     * @param array $params
     *
     * @return array ['call_defined' => string, 'call_params' => string]
     */
    protected function generateTestCaseCallParam($params)
    {
        $callDefined = '';
        $callParams = [];
        foreach ($params as $name => $param) {
            if (!is_null($param['const'])) {
                $callDefined .= "\${$name} = {$param['const']};\n";
            } else {
                $callDefined .= "\${$name} = " . $this->transferValueToExpr($param['value']) . ";\n";
            }

            $callParams[] = "\${$name}";
        }

        return [
            'call_defined' => $callDefined,
            'call_params' => implode(', ', $callParams),
        ];
    }

    /**
     * Transfer mixed value to string expression.
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function transferValueToExpr($value)
    {
        switch (gettype($value)) {
            case 'integer':
            case 'double':
                return $value;
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'string':
                return "'{$value}'";
            case 'array':
                return $this->transferExprArray($value);
            case 'NULL':
                return 'null';
            case 'resource':
                return 'null';
            case 'object':
                try {
                    return 'new ' . get_class($value);
                } catch (Throwable $e) {
                    return 'null';
                }
        }
    }

    /**
     * Transfer array value to string expression using var_export and replacing the format.
     *
     * @param array $arr
     *
     * @return string
     */
    protected function transferExprArray($arr)
    {
        $expr = var_export($arr, true);
        $expr = preg_replace('/\d+\s=>\s/', '', $expr);
        $expr = str_replace('array (', '[', $expr);
        $expr = preg_replace('/(\),?\n|\)$)/', ']', $expr);
        $expr = preg_replace('/(\n|\s)/', '', $expr);

        return $expr;
    }

    /**
     * Generate params.
     *
     * @param array $params
     * @param boolean $usingDefault
     *
     * @return array
     */
    protected function generateParamsMock($params, $usingDefault = false)
    {
        $data = [];
        foreach ($params as $param) {
            $data[$param['name']] = $this->generateParamMock($param, $usingDefault);
        }

        return $data;
    }

    /**
     * Generate param.
     *
     * @param array $param
     * @param boolean $usingDefault
     *
     * @return array
     */
    protected function generateParamMock($param, $usingDefault = false)
    {
        $value = ['value' => null, 'const' => null];
        if ($usingDefault && !is_null($param['default'])) {
            if (!is_null($param['default']['const'])) {
                $value['const'] = $param['default']['const'];
            }
            $value['value'] = $param['default']['value'];

            return $value;
        }

        $value['value'] = $this->generateParamByType($param['type']);

        return $value;
    }

    /**
     * Generate param by it`s type.
     *
     * @param array $type
     *
     * @return mixed
     */
    protected function generateParamByType($type)
    {
        if ($type['build_in']) {
            switch ($type['name']) {
                case 'int':
                    return MockPhp::number();
                case 'string':
                    return MockPhp::string();
                case 'float':
                    return MockPhp::float()();
                case 'array':
                    return [
                        MockPhp::number(),
                        MockPhp::string(),
                    ];
                case 'boolean':
                    return MockPhp::boolean();
                case 'callable':
                    return function () {
                    };
                default:
                    return MockPhp::string();
            }
        }

        try {
            return new $type['name'];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param array $data
     * @param array $params
     *
     * @return mixed the result will be print by var_export
     */
    protected function generateMethodReturn($data, $params)
    {
        $values = array_map(function ($item) {
            return $item['value'];
        }, $params);

        try {
            if ($data['is_static']) {
                $callable = "{$data['class_full_name']}::{$data['method']}";
            } else {
                $class = new $data['class_full_name'];
                $callable = [$class, $data['method']];
            }
            return call_user_func_array($callable, $values);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * @param string $mode
     *
     * @return string
     */
    protected function transferChoiceMode($mode)
    {
        $map = [
            'skip' => 'skip',
            'back' => 'backup',
            'repl' => 'replace',
        ];

        return $map[mb_substr($mode, 0, 4)];
    }

    /**
     * @param string $sourceName the class source filename
     * @param string $filename the new test case filename
     * @param string $code test case code
     * @param string $mode repeat file process mode
     */
    protected function saveTestCaseByMode($sourceName, $filename, $code, $mode)
    {
        switch ($mode) {
            case 'skip':
                $this->writeln("<info>skip <comment>{$filename}</comment></info>");
                break;
            case 'replace':
                file_put_contents($filename, $code);
                $this->handleTestCaseCount++;
                $this->writeln("<info>replace <comment>{$sourceName}</comment> -> <comment>{$filename}</comment></info>");
                break;
            case 'backup':
                $backupName = mb_substr($filename, 0, -5) . '_' . date('YmdHis') . '.phpt';
                copy($filename, $backupName);
                file_put_contents($filename, $code);
                $this->handleTestCaseCount++;
                $this->writeln("<info>processed <comment>{$sourceName}</comment> -> (backup <comment>{$backupName}</comment> and new create <comment>{$filename}</comment></info>)");
                break;
        }
    }

    /**
     * Get the test case phpt template.
     *
     * @return string
     */
    public static function getTemplate()
    {
        return static::$template;
    }

    /**
     * Set the test case phpt templete.
     *
     * @param string $template
     */
    public static function setTemplate($template)
    {
        static::$template = $template;
    }

    /**
     * Parset template.
     *
     * @param string $template
     * @param array $params
     *
     * @return string
     */
    protected function parseTemplate($template, $params = [])
    {
        return preg_replace_callback('/\{\{(.*?)\}\}/', function ($m) use ($params) {
            return $params[trim($m[1])] ?? '{{' . $m[1] . '}}';
        }, $template);
    }

    /**
     * Get the new code save path.
     *
     * @param string $cwd code path
     * @param string $source code source directory
     * @param string $output code save directory
     *
     * @return string code new path
     */
    protected function getSavePath($data)
    {
        $sourceName = $data['filename'];
        $path = $this->outputPath . mb_substr(dirname($sourceName), mb_strlen($this->sourcePath)) . '/' . $data['class'];
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path . "/{$data['method']}.phpt";
    }

    /**
     * Initializa PHP-Parser instance.
     */
    protected function initParser()
    {
        $lexer = new Lexer([
            'usedAttributes' => ['comments'],
        ]);

        $factory = new ParserFactory;

        $this->parser = $factory->create(ParserFactory::ONLY_PHP7, $lexer);
    }

    /**
     * Get the PHP-Parser instance.
     *
     * @return Parser
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * Get the symfony console instance.
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Get the symfony console style instance.
     *
     * @return SymfonyStyle
     */
    public function getIo()
    {
        return $this->io;
    }

    /**
     * Output log and line feed.
     *
     * @param string $msg
     */
    public function writeln($msg)
    {
        $this->log($msg, "writeln");
    }

    /**
     * Output log.
     * if there doesn`s have symfony output instance, then console log by echo.
     *
     * @param string $msg
     * @return string $method
     */
    public function log($msg, $method = "write")
    {
        if (is_null($this->getOutput())) {
            echo $method == 'writeln' ? $msg . PHP_EOL : $msg;
        } else {
            call_user_func([$this->getOutput(), $method], $msg);
        }
    }
}
