<?php
namespace SPF\Rpc;

use SPF\Exception\Exception;
use SPF\Rpc\Protocol\TcpProtocol;
use SPF\Rpc\Protocol\HttpProtocol;
use SPF\Rpc\Protocol\ProtocolHeader;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;
use Closure;
use SPF\Rpc\Formatter\FormatterFactory;
use SPF\Rpc\Tool\Helper;
use Swoole;

class Server
{
    use TcpProtocol, HttpProtocol;

    /**
     * SDK版本号
     */
    const SDK_VERSION = 10000;

    /**
     * Swoole Server对象
     *
     * @var \Swoole\Server|\Swoole\Http\Server|\Swoole\WebSocket\Server
     */
    public $server = null;

    /**
     * @var \Symfony\Component\Console\Output\ConsoleOutput
     */
    public $consoleOutput = null;

    /**
     * 中间件，[protocol1 => [middleware1, middleware2], protocol2 => []]格式
     *
     * @var array
     */
    protected $middlewares = [];

    public function __construct()
    {
        $this->initMiddlewares();
    }

    /**
     * 初始化中间件，将中间件从配置文件读取分配到各个协议中
     */
    protected function initMiddlewares()
    {
        $common = Config::get('app.middleware', []);
        foreach (Config::get('app.server') as $server) {
            $this->middlewares[$server['protocol']] = array_merge($common, $server['middleware'] ?? []);
        }
    }

    /**
     * 执行中间件
     *
     * @param array $request
     * @param string $protocol
     *
     * @return mixed same as callFunction
     */
    protected function handleMiddleware(&$request, $protocol)
    {
        $middlewares = $this->middlewares[$protocol] ?? [];
        $pipe = array_reduce(array_reverse($middlewares), function ($carry, $item) {
            return function (&$request) use ($carry, $item) {
                // return call_user_func($item, $request, $carry);
                return $item($request, $carry);
            };
        }, $this->initPipeline());

        return $pipe($request);
    }

    /**
     * 执行请求
     */
    protected function handleRequest($protocol, $data, $clientInfo = [], &$protocolHeader = [])
    {
        $dePacket = $this->decodePacket($data);
        $protocolHeader = $dePacket['header'];

        $request = FormatterFactory::decodeRequest($dePacket['header']['formatter'], $dePacket['body']);
        $request['packet_header'] = $protocolHeader;
        $request['client'] = $clientInfo;

        $responseData = $this->handleMiddleware($request, $protocol);

        $responseFmt = FormatterFactory::encodeResponse($dePacket['header']['formatter'], $responseData, $request);

        return $this->encodePacket($responseFmt, $dePacket['header'], 0);
    }

    protected function callFunction(&$request)
    {
        $class = new $request['class'];
        $response = call_user_func_array([$class, $request['function']], $request['req_params']);

        return $response;
    }

    /**
     * 初始化中间件管道
     *
     * @return \Closure
     */
    protected function initPipeline()
    {
        return function (&$request) {
            return $this->callFunction($request);
        };
    }

    protected function encodePacket($response, $reqHeader, $errno = 0)
    {
        return ProtocolHeader::encode(
            $response,
            strlen($response),
            $reqHeader['formatter'],
            $errno,
            $reqHeader['request_id'],
            $reqHeader['uid'],
            Config::get('app.version'),
            self::SDK_VERSION
        );
    }

    protected function decodePacket($request)
    {
        return ProtocolHeader::decode($request);
    }

    protected function beforeStart(\Swoole\Server $server)
    {
    }

    /**
     * 启动Server
     */
    public function start()
    {
        // 创建servers
        foreach (Config::get('app.server', []) as $conn) {
            switch ($conn['protocol']) {
                case 'tcp':
                    $this->createTcpServer($conn);
                    break;
                case 'http':
                    $this->createHttpServer($conn);
                    break;
                default:
                    throw new Exception("not support protocol: {$conn['protocol']}");
            }
        }

        if (is_null($this->server)) {
            throw new Exception("you must configure one server at least one");
        }

        // 注册公共事件监听
        $this->server->on('Start', [$this, 'onStart']);
        $this->server->on('Shutdown', [$this, 'onShutdown']);
        $this->server->on('ManagerStart', [$this, 'onManagerStart']);
        $this->server->on('ManagerStop', [$this, 'onManagerStop']);
        $this->server->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->server->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->server->on('WorkerExit', [$this, 'onWorkerExit']);
        $this->server->on('WorkerError', [$this, 'onWorkerError']);

        if (Config::get('app.hotReload.driver')) {
            $this->server->addProcess($this->getHostReloadProcess($this->server));
            $this->console()->writeln("<comment>hot reload is enable, changed code will reload without manual reloading server</comment>");
        }

        // 暴露hook
        $this->beforeStart($this->server);

        $serviceName = Config::get('app.name');
        $pid = posix_getpid();
        $this->console()->writeln("<info>{$serviceName} is running at {$pid}...</info>");

        $this->server->start();
    }


    public function onStart(\Swoole\Server $server)
    {
        Helper::setProcessName("rpc-server: master");

        // 保存PID文件
        $pid = posix_getpid();
        $pidFile = Config::get('app.serverPid');
        file_put_contents($pidFile, $pid);

        // do something
    }

    public function onShutdown(\Swoole\Server $server)
    {
        // 移除PID文件
        $pidFile = Config::get('app.serverPid');
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        // do something
    }

    public function onManagerStart(\Swoole\Server $serv)
    {
        Helper::setProcessName("rpc-server: mamager");

        // do something
    }

    public function onManagerStop(\Swoole\Server $serv)
    {
        // do something
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        Helper::setProcessName("rpc-server: worker");

        // 清理Opcache缓存
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        // 清理APC缓存
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }

        // do something
    }

    public function onWorkerStop(\Swoole\Server $server, int $workerId)
    {
        // do something
    }

    public function onWorkerExit(\Swoole\Server $server, int $workerId)
    {
        // do something
    }

    public function onWorkerError(\Swoole\Server $serv, int $workerId, int $workerPid, int $exitCode, int $signal)
    {
        // do something
    }

    /**
     * Symfony终端输出美化组件
     *
     * @return \Symfony\Component\Console\Output\ConsoleOutput
     */
    protected function console()
    {
        if (is_null($this->consoleOutput)) {
            $this->consoleOutput = new ConsoleOutput();
        }

        return $this->consoleOutput;
    }

    /**
     * 用于debug输出exception到终端
     *
     * @param \Throwable $e
     */
    protected function debugExceptionOutput(Throwable $e)
    {
        $msg = $e->getMessage();
        $code = $e->getCode();

        // RpcException独有getContext方法，保存异常上下文信息
        if (method_exists($e, 'getContext')) {
            $context = json_encode($e->getContext(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $context = '';
        }

        $this->console()->writeln("<comment>  >Error: [{$code}] {$msg} </comment>");
        if ($context) {
            $this->console()->writeln("<comment>  >Context: {$context}</comment>");
        }
        foreach (explode("\n", $e->getTraceAsString()) as $line) {
            $this->console()->writeln("<comment>    >{$line}</comment>");
        }
    }

    /**
     * @return \swoole_process
     */
    protected function getHostReloadProcess(\Swoole\Server $server)
    {
        $driver = Config::get('app.hotReload.driver');
        $hotReload = new $driver(Config::$rootPath, Config::get('app.hotReload', []));
        $enableLog = Config::get('app.hotReload.log', true);

        $hotReload->setCallback(function ($logs) use ($enableLog, $server) {
            if ($enableLog) {
                foreach ($logs as $log) {
                    echo $log, PHP_EOL;
                }
            }
            $server->reload();
        });

        return $hotReload->make();
    }
}
