<?php

namespace SPF\Validator;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use PhpParser\ParserFactory;
use PhpParser\Parser;
use PhpParser\Lexer;
use PhpParser\Node\Stmt;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use ReflectionProperty;

class ValidateRpcMethodParams
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
     * Test cases source path.
     * 
     * @var string
     */
    protected $rootPath = null;

    /**
     * Find error count.
     * 
     * @var int
     */
    protected $errorCount = 0;

    /**
     * Allowed class method return type
     * 
     * @var array
     */
    protected static $allowedReturnType = [
        'int', 'array', 'string', 'float', 'bool',
    ];

    /**
     * Allowed class method return structObject
     * 
     * @var bool
     */
    protected static $allowReturnStructObject = true;

    /**
     * Allowed class method param type
     * 
     * @var array
     */
    protected static $allowedParamType = [
        'int', 'array', 'string', 'float', 'bool', 'callable',
    ];

    /**
     * Allowed class method param structObject
     * 
     * @var bool
     */
    protected static $allowParamStructObject = true;

    /**
     * Allowed whitelist include files and classes
     * 
     * @param array
     */
    protected static $allowWhitelist = [
        'file' => [],
        'class' => [],
    ];

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output = null)
    {
        if (is_null($output)) {
            $output = new ConsoleOutput();
        }
        $this->output = $output;

        $this->initParser();
    }

    /**
     * Set allowed params types.
     * 
     * @param array $types
     */
    public static function setAllowedParamType(array $types)
    {
        static::$allowedParamType = $types;
    }

    /**
     * Set allowed return types.
     * 
     * @param array $types
     */
    public static function setAllowedReturnType(array $types)
    {
        static::$allowedReturnType = $types;
    }

    /**
     * Set allow return structObject type.
     * 
     * @param bool $allow
     */
    public static function setAllowReturnStructObject(bool $allow = false)
    {
        static::$allowReturnStructObject = $allow;
    }

    /**
     * Set allow param structObject type.
     * 
     * @param bool $allow
     */
    public static function setAllowParamStuctObject(bool $allow = false)
    {
        static::$allowParamStructObject = $allow;
    }

    /**
     * Set allow whitelist.
     * 
     * @param array $whitelist
     * @param string $categary file|class
     */
    public static function setAllowedWhitelist(array $whitelist, string $categary = 'file')
    {
        static::$allowWhitelist[$categary] = $whitelist;
    }

    /**
     * Handle.
     * 
     * @param string $root
     */
    public function handle($root)
    {
        $this->rootPath = $root;
        $this->errorCount = 0;

        $this->writeln("<info>start check methods`s params of classes<info>");

        $this->validate();

        return $this->errorCount === 0;
    }

    public function validate()
    {
        $dir = new RecursiveDirectoryIterator($this->rootPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);

        foreach ($files as $file) {
            if ($this->isWhitelistFile($file) === true) {
                continue;
            }
            $code = file_get_contents($file);
            $stmts = $this->getParser()->parse($code);
            $this->recursiveReadStmts($stmts, $file);
        }
    }

    /**
     * Determine if the file is a whitelist.
     * 
     * @param string $file
     * 
     * @return boolean
     */
    protected function isWhitelistFile($file)
    {
        foreach(static::$allowWhitelist['file'] as $whiteFile) {
            $whiteFilename = strpos($whiteFile, '/') === 0 ? $whiteFile : $this->rootPath . '/' . $whiteFile;
            if (strpos($file, $whiteFilename) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the class is a whitelist.
     * 
     * @param string $class
     * 
     * @return boolean
     */
    protected function isWhitelistClass($class)
    {
        foreach (static::$allowWhitelist['class'] as $whiteClass) {
            if (class_exists($whiteClass) && ($class === $whiteClass || is_subclass_of($class, $whiteClass))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $stmts php-parser stmts array
     * @param int $classCount class count
     */
    protected function recursiveReadStmts($stmts, $file, &$classCount = 0, &$namespace = null, &$class = null)
    {
        foreach ($stmts as $node) {
            // get the code file`s namesapce
            if ($node instanceof Stmt\Namespace_) {
                $namespace = (string) $node->name;
            }

            // get the class name
            if (($node instanceof Stmt\Class_) || ($node instanceof Stmt\Interface_) || ($node instanceof Stmt\Trait_)) {
                $class = (string) $node->name;
                $classFullName = $this->getClassFullName($class, $namespace);

                if ($this->isWhitelistClass($classFullName) === true) {
                    continue;
                }

                $classCount++;

                // if class great than 1, then throw error
                if ($classCount > 1) {

                    $this->writeln("<error>同一个文件 [{$file}] 中不允许定义超过一个类 [{$classFullName}]</error>");
                    $this->errorCount++;
                }

                // check methods`s return type and param type
                $this->validateTypeByReflection($classFullName);
                continue;
            }

            // recursive read stmts if there is any other class
            if (isset($node->stmts)) {
                $this->recursiveReadStmts($node->stmts, $file, $classCount, $namespace, $class);
                continue;
            }
        }
    }

    /**
     * @param string $classFullName
     */
    protected function validateTypeByReflection($classFullName)
    {
        $refClass = new ReflectionClass($classFullName);
        
        foreach($refClass->getMethods() as $refMethod) {
            // if not public, continue
            if (!$refMethod->isPublic() || $refMethod->class !== $classFullName) {
                continue;
            }

            $methodName = $refMethod->getName();
            $methodDesc = "<comment>[{$classFullName}::{$methodName}]</comment>";

            $allowedParamType = $this->getAllowedParamType();
            $allowedParamTypeString = implode(', ', $allowedParamType);

            foreach($refMethod->getParameters() as $refParam) {
                $paramName = $refParam->getName();
                $paramType = (string)$refParam->getType();

                $validFlag = $this->validateTypeResult($paramType, $allowedParamType, static::$allowParamStructObject);
                $this->validateTypeAndThrowErrors(
                    $validFlag,
                    $methodDesc,
                    $paramType,
                    "参数 <comment>$paramName</comment> ",
                    $allowedParamTypeString
                );
            }

            $allowedReturnType = $this->getAllowedReturnType();
            $allowedReturnTypeString = implode(', ', $allowedReturnType);

            $returnType = (string)$refMethod->getReturnType();

            $validFlag = $this->validateTypeResult($returnType, $allowedReturnType, static::$allowReturnStructObject);
            $this->validateTypeAndThrowErrors(
                $validFlag,
                $methodDesc,
                $returnType,
                "返回值",
                $allowedReturnTypeString
            );
        }
    }

    /**
     * @param string $validFlag
     * @param string $methodDesc
     * @param string $type
     * @param string $typeDesc
     * @param string $allowedTypeString
     */
    protected function validateTypeAndThrowErrors($validFlag, $methodDesc, $type, $typeDesc, $allowedTypeString)
    {
        switch ($validFlag) {
            case 'empty':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型不能为空</error>"
                );
                $this->errorCount++;
                break;
            case 'not_in_provided':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不在允许范围内 <comment>[$allowedTypeString]</comment></error>"
                );
                $this->errorCount++;
                break;
            case 'trait':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能为Trait</error>"
                );
                $this->errorCount++;
                break;
            case 'interface':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能为Interface</error>"
                );
                $this->errorCount++;
                break;
            case 'has_methods':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能包含任何方法</error>"
                );
                $this->errorCount++;
                break;
            case 'has_static_props':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能包含静态属性</error>"
                );
                $this->errorCount++;
                break;
            case 'has_consts':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能包含任何常量定义</error>"
                );
                $this->errorCount++;
                break;
            case 'prop_not_public':
                $this->writeln(
                    "<error>方法 $methodDesc 的{$typeDesc}类型 <comment>{$type}</comment> " .
                        "不能有私有属性</error>"
                );
                $this->errorCount++;
                break;
        }
    }

    /**
     * Validate methods`s params type and return type.
     * 
     * @param string $type
     * @param array $allowedTypes
     * @param bool $allowStructObject
     * 
     * @return string
     */
    protected function validateTypeResult($type, array $allowedTypes, $allowStructObject = true)
    {
        if (!$type && (!in_array(null, $allowedTypes) && !in_array('', $allowedTypes))) {
            return 'empty';
        }
        if (class_exists($type)) {
            if ($this->isWhitelistClass($type) === true) {
                return 'ok';
            }
            if ($allowStructObject) {
                return $this->validateTypeStructObject($type, $allowedTypes);
            }
        } 
        if (!in_array($type, $allowedTypes)) {
            return 'not_in_provided';
        }

        return 'ok';
    }

    /**
     * Validate struct object.
     * 
     * @param string $class
     * @param array $allowedTypes
     * 
     * @return string
     */
    protected function validateTypeStructObject($class, $allowedTypes = [])
    {
        // Validate class in array or extends someone class in array
        foreach($allowedTypes as $allowedType) {
            if (class_exists($allowedType) && ($class === $allowedType || is_subclass_of($class, $allowedType))) {
                return 'ok';
            }
        }

        $refClass = new ReflectionClass($class);
        if ($refClass->isTrait()) {
            return 'trait';
        }
        if ($refClass->isInterface()) {
            return 'interface';
        }
        foreach($refClass->getMethods() as $refMethod) {
            if ($refMethod->getName() !== '__construct') {
                return 'has_methods';
            }
        }
        if (count($refClass->getStaticProperties()) > 0) {
            return 'has_static_props';
        }
        if (count($refClass->getConstants()) > 0) {
            return 'has_consts';
        }
        foreach($refClass->getProperties() as $refProp) {
            if (!$refProp->isPublic()) {
                return 'prop_not_public';
            }
        }

        return 'ok';
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
     * Get allowed class method return type.
     * 
     * @return array
     */
    protected function getAllowedReturnType()
    {
        return static::$allowedReturnType;
    }

    /**
     * Get allowed class method param type.
     * 
     * @return array
     */
    protected function getAllowedParamType()
    {
        return static::$allowedParamType;
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
