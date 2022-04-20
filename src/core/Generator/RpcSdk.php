<?php

namespace SPF\Generator;

use PhpParser\ParserFactory;
use PhpParser\Parser;
use PhpParser\Lexer;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Stmt;
use Symfony\Component\Console\Output\OutputInterface;
use SPF\Generator\PrettyPrinter\ForRpcSdk;
use SPF\Exception\LogicException;
use SPF\Generator\RpcSdk\RpcClient;
use ReflectionClass;

class RpcSdk
{
    /**
     * @var Parser
     */
    protected $parser = null;

    /**
     * @var ForRpcSdk
     */
    protected $pretty = null;

    /**
     * Symfony console output instance
     * 
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * RPC Service name.
     * 
     * @var string
     */
    protected static $serviceName = 'Demo';

    /**
     * SDK new namespace prefix.
     * 
     * @var string
     */
    public static $newNamespacePrefix = 'Xes\\RpcSdk\\';

    /**
     * RPC API namespace prefix.
     * 
     * @var string
     */
    public static $namespacePrefix = 'Demo\\';

    /**
     * Need remove namespace list.
     * 
     * @var array
     */
    public static $removeNamespaces = [];

    /**
     * User defined stmts to filter sdk generate.
     * 
     * @var array
     */
    protected static $udfStmts = [
        'call_in_method' => 'return $this->callRpc(__METHOD__, func_get_args());',
        'call_in_static_method' => 'return static::staticCallRpc(__METHOD__, func_get_args());',
        'namespace_using' => null,
        'static_rpc_client_call' => <<<'CODE'
/**
 * 远程调用RPC，禁止修改
 */
protected function callRpc($method, $args)
{
    return RpcClient::call($method, $args);
}

/**
 * 远程调用RPC，禁止修改
 */
protected static function staticCallRpc($method, $args)
{
    return RpcClient::call($method, $args, true);
}
CODE
        ,    
    ];

    /**
     * except automatic generate files appended other files
     * format: source => target
     */
    protected static $appendFiles = [];

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output = null)
    {
        $this->output = $output;

        $this->initParser();
        $this->initPretty();
    }

    /**
     * Set user defined stmts.
     * 
     * @param string the stmts`s name
     * @param string the stmts`s code, don`t begin with '<?php' tag
     */
    public static function setUdfStmt($name, $expr)
    {
        static::$udfStmts[$name] = $expr;
    }

    /**
     * Set sdk append files.
     * 
     * @param array $files
     */
    public static function setAppendFile(array $files)
    {
        static::$appendFiles = $files;
    }

    /**
     * Set RPC API`s namespace prefix. eg: Demo\\
     * 
     * @param string $namespacePrefix
     */
    public static function setNamespacePrefix(string $namespacePrefix)
    {
        static::$namespacePrefix = $namespacePrefix;
    }

    /**
     * Set RPC API sdk`s new namespace prefix. eg: Xes\\RpcSdk\\
     * 
     * @param string $newNamespacePrefix
     */
    public static function setNewNamespacePrefix(string $newNamespacePrefix)
    {
        static::$newNamespacePrefix = $newNamespacePrefix;
    }

    /**
     * Set RPC service name.
     * 
     * @param string $serviceName
     */
    public static function setServiceName(string $serviceName)
    {
        static::$serviceName = $serviceName;
    }

    /**
     * Set RPC API sdk`s need removing namespace rules, supportting regex pattern. eg: Foo\Bar\* or Foo\Bar
     * 
     * @param string $removeNamespaces
     */
    public static function setRemoveNamespaces(string $removeNamespaces)
    {
        static::$removeNamespaces = $removeNamespaces;
    }

    /**
     * Handle.
     * 
     * @param string $src
     * @param string $output
     * @param string $output
     */
    public function handle($src, $output, $libdir = 'src')
    {
        // $this->setDefaultAppendFiles($libdir);

        $this->setSpecialStmts($this->getPretty());

        $this->writeln("<info>start process<info>");


        $handleCount = 0;
        $this->recursiveHandle($src, $src, $output.'/'.$libdir, $handleCount);

        $this->writeln("<info>processed file $handleCount<info>");

        $this->appendSdkFiles($output, $libdir);

        $this->writeln("<info>append new files into sdk<info>");
    }

    /**
     * Set default append files.
     * 
     * @param string $libdir
     */
    protected function setDefaultAppendFiles($libdir)
    {
        if (empty(static::$appendFiles)) {
            static::$appendFiles = [
                getcwd() . '/composer.json' => 'composer.json',
                RpcClient::class => $libdir.'/RpcClient.php',
            ];
        }
    }

    /**
     * Recursive handle generate opration.
     * 
     * @param string $dirSrc
     * @param string $source the sdk source path
     * @param string $output the sdk output path
     * @param int $handleCount processed file`s count
     */
    protected function recursiveHandle($dirSrc, $source, $output, &$handleCount)
    {
        $dir = dir($dirSrc);
        while($file = $dir->read()) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $path = "$dirSrc/$file";
            if(is_dir($path)) {
                $this->recursiveHandle($path, $source, $output, $handleCount);
                continue;
            }
            
            $code = file_get_contents($path);
            $newCode = $this->processCode($code);
            $prependNamespacePrefix = $this->getPretty()->prependNamespacePrefix;

            $savePath = $this->getSavePath($path, $source, $output, $prependNamespacePrefix);
            file_put_contents($savePath, $newCode);

            $handleCount++;
            $this->writeln("<info>processed <comment>$path</comment> -> <comment>$savePath</comment><info>");
        }
        $dir->close();
    }

    /**
     * Get the new code save path.
     * 
     * @param string $cwd code path
     * @param string $source code source directory
     * @param string $output code save directory
     * @param bool $prependNamespacePrefix the class whether prepend namespace prefix
     * 
     * @return string code new path
     */
    protected function getSavePath($cwd, $source, $output, $prependNamespacePrefix = true)
    {
        $filename = $output  . ($prependNamespacePrefix ? '/api' : '/struct') . mb_substr($cwd, mb_strlen($source));
        $path = dirname($filename);
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $filename;
    }

    /**
     * Append other new sdk files such as RpcClient class.
     * 
     * @param string $output
     * @param string $libdir
     */
    protected function appendSdkFiles($output, $libdir)
    {
        $namespacePrefix = static::$namespacePrefix;
        $sourceNamespacePrefixDouble = str_replace('\\', '\\\\', $namespacePrefix);
        $newNamespacePrefix = static::$newNamespacePrefix;
        $fullPrefix = $newNamespacePrefix . $namespacePrefix;
        $fullPrefixDouble = str_replace('\\', '\\\\', $fullPrefix);
        $fullNamespace = mb_substr($fullPrefix, -1) === '\\' ? mb_substr($fullPrefix, 0, -1) : $fullPrefix;

        foreach(static::$appendFiles as $source => $target) {
            // if the file is class name, then using reflection find the real filename
            if (strpos($source, '\\') !== false && class_exists($source)) {
                $source = (new ReflectionClass($source))->getFileName();
            }
            // provide the placeholder to replacing by var libdir
            $target = str_replace('%%libdir%%', $libdir, $target);

            $filename = $output.'/'.$target;

            $code = file_get_contents($source);
            $code = str_replace('%%namespace%%', $fullNamespace, $code);
            $code = str_replace('%%namespacePrefix%%', $fullPrefix, $code);
            $code = str_replace('%%namespacePrefixDouble%%', $fullPrefixDouble, $code);
            $code = str_replace('%%sourceNamespacePrefix%%', $namespacePrefix, $code);
            $code = str_replace('%%sourceNamespacePrefixDouble%%', $sourceNamespacePrefixDouble, $code);
            $code = str_replace('%%serviceName%%', static::$serviceName, $code);
            file_put_contents($filename, $code);
       
            $this->writeln("<info>copy <comment>$source</comment> -> <comment>$filename</comment><info>");
        }
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
     * Initialize pretty instance.
     */
    protected function initPretty()
    {
        $this->pretty = new ForRpcSdk;
    }

    /**
     * Get the pretty instance.
     * 
     * @return ForRpcSdk
     */
    public function getPretty()
    {
        return $this->pretty;
    }

    /**
     * Get the replace method content stmts.
     * 
     * @return Stmt
     */
    protected function getCallInMethodStmts()
    {
        $code = "<?php\n\n" . static::$udfStmts['call_in_method'];
        
        return $this->parseCodeToStmts($code);
    }

    /**
     * Get the replace static method content stmts.
     * 
     * @return Stmt
     */
    protected function getCallInStaticMethodStmts()
    {
        $code = "<?php\n\n" . static::$udfStmts['call_in_static_method'];
        
        return $this->parseCodeToStmts($code);
    }

    /**
     * Get the append namespace use stmts.
     * 
     * @return Stmt
     */
    protected function getNamespaceUsingStmts()
    {
        $code = "<?php\n\n" . static::$udfStmts['namespace_using'];
        
        return $this->parseCodeToStmts($code);
    }

    /**
     * Get the rpc static call method stmts appended the class.
     * 
     * @return array
     */
    protected function getStaticRpcClientStmts()
    {
        if (is_null(static::$udfStmts['static_rpc_client_call'])) {
            return [];
        }
        
        $code = "<?php\n\nclass A {\n" . static::$udfStmts['static_rpc_client_call'] . "}\n\n";
    
        $stmts = $this->parseCodeToStmts($code);

        // extract the method from the class
        foreach($stmts as $node) {
            if ($node instanceof Stmt\ClassMethod) {
                $stmts = $node;
                break;
            }
            if ($node->stmts) {
                $stmts = $node->stmts;
            }
        }

        foreach($stmts as $stmt) {
            // set the special flag and skip the next filter
            $stmt->setAttribute('special', true);
        }

        return $stmts;
    }

    /**
     * Parse the template code to stmts.
     * 
     * @param string $code
     * 
     * @return Stmt[]|Stmt
     */
    protected function parseCodeToStmts($code)
    {
        $stmts = $this->getParser()->parse($code);

        if (is_null($stmts)) {
            throw new LogicException('Template syntax error');
        }
        
        return $stmts;
    }

    /**
     * Set the special stmts.
     * expose the $pretty object can be debug
     * 
     * @param Standard $pretty
     */
    public function setSpecialStmts($pretty)
    {
        $pretty->setSpecialStmt('replaceMethod', $this->getCallInMethodStmts());
        $pretty->setSpecialStmt('replaceStaticMethod', $this->getCallInStaticMethodStmts());
        $pretty->setSpecialStmt('appendCallRpc', $this->getStaticRpcClientStmts());
        $pretty->setSpecialStmt('appendNamespaceUsing', $this->getNamespaceUsingStmts());
    }

    /**
     * Process code.
     * 
     * @param string $code the source code
     * 
     * @return string the processed code
     */
    protected function processCode($code)
    {
        $stmts = $this->getParser()->parse($code);

        $this->getPretty()->prependNamespacePrefix = true;

        return $this->getPretty()->prettyPrintFile($stmts);
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
