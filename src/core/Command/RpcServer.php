<?php

namespace SPF\Command;

use SPF\Exception\Exception;
use SPF\Rpc\Config;
use SPF\Rpc\Server;
use SPF\Rpc\Tool\ReflectionClassMap;
use Swoole\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;

class RpcServer extends Command
{
    protected function configure()
    {
        $this->setName('rpc:server')
            ->setDescription('RPC Server Manager.')
            ->setHelp('You can manage your RPC server by this command')
            ->setDefinition(
                new InputDefinition([
                    new InputArgument('action', InputArgument::REQUIRED, 'start|stop|restart|reload|kill|info')
                ])
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $action = $input->getArgument('action');
        $this->validAction($action);

        $this->runAction($action);
    }

    protected function runAction($action)
    {
        $method = 'action' . ucfirst($action);
        $this->{$method}();
    }

    /**
     * 验证action参数是否合法
     */
    protected function validAction($action)
    {
        if (!in_array($action, ['start', 'stop', 'restart', 'reload', 'kill', 'info'])) {
            throw new Exception("Action Argument is invalidate, please input it in start|stop|restart|reload|kill|info");
        }
    }

    /**
     * 启动服务
     */
    protected function actionStart()
    {
        if (($pid = $this->getPid()) && $this->isRunning($pid)) {
            throw new Exception("RPC Server is running on {$pid}");
        }

        $this->info('> Starting RPC Server...');

        ReflectionClassMap::initMap();

        $serverName = Config::get('app.serverClass');
        if (is_null($serverName)) {
            throw new Exception("RPC Server Class不能为空");
        }

        $server = new $serverName();
        if (!($server instanceof Server)) {
            throw new Exception("RPC Server 必须是 " . Server::class . " 或者其子类");
        }

        $this->consoleIfDaemon();

        $server->start();
    }

    /**
     * 停止服务
     */
    protected function actionStop()
    {
        $pidFile = $this->pidFile();
        $pid = $this->getPid($pidFile);

        if (!$this->isRunning($pid)) {
            throw new Exception("Failed! There is no RPC Server running.");
        }

        $this->info('Stopping RPC Server...');

        $isRunning = $this->killProcess($pid, SIGTERM, 15);

        if ($isRunning) {
            throw new Exception("Unable to stop the RPC Server.");
        }

        if (file_exists($pidFile)) {
            unlink($pidFile);
        }

        $this->info('> success');
    }

    /**
     * 重启服务
     */
    protected function actionRestart()
    {
        $pid = $this->getPid();

        if ($this->isRunning($pid)) {
            $this->actionStop();
        }

        $this->actionStart();
    }

    /**
     * 重载服务，只重启worker进程
     */
    protected function actionReload()
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            throw new Exception("Failed! There is no RPC Server running.");
        }

        $this->info('> Reloading RPC Server...');

        $isRunning = $this->killProcess($pid, SIGUSR1);

        if (!$isRunning) {
            throw new Exception("Reload Rpc Server Failed!");
        }

        $this->info('> success');
    }

    /**
     * 强制杀死进程
     */
    protected function actionKill()
    {
        $pid = $this->getPid();

        if (!$this->isRunning($pid)) {
            throw new Exception("Failed! There is no RPC Server running.");
        }

        $this->info('> Force Killing RPC Server...');

        exec('ps -ef | grep "rpc-server" | awk "{print $2}" | xargs kill -9', $output);
        $this->removePidFile();
        foreach($output as $line) {
            $this->info($line);
        }

        $this->info('> success');
    }

    /**
     * 输出服务信息
     */
    protected function actionInfo()
    {
        $serverClass = Config::get('app.serverClass');
        $sdkVersion = $serverClass::SDK_VERSION;

        $listens = array_map(function($item) {
            return $item['protocol'] . '://' . $item['host'] . ':' . $item['port'];
        }, Config::get('app.server'));
        $pid = $this->getPid();
        $serverStatus = $this->isRunning($pid);

        $header = ['Name', 'Value'];
        $body = [
            ['PHP Version', PHP_VERSION],
            ['Swoole Version', SWOOLE_VERSION],
            ['SDK Version', $sdkVersion],
            ['Server Version', Config::get('app.version')],
            ['Listens', implode(', ', $listens)],
            ['Server Status', $serverStatus ? 'Online' : 'Offline'],
            ['PID', $serverStatus ? $pid : 'None'],
        ];

        $this->table($header, $body);
    }

    /**
     * 获取PID文件路径
     * 
     * @return string
     */
    protected function pidFile()
    {
        $pid = Config::get('app.serverPid');
        if (empty($pid)) {
            throw new Exception("PID文件不能为空");
        }

        return $pid;
    }

    /**
     * 获取Server的PID，PID不存在时为0
     * 
     * @return int
     */
    protected function getPid($pidFile = null)
    {
        if (is_null($pidFile)) {
            $pidFile = $this->pidFile();
        }
        if (!file_exists($pidFile)) {
            return 0;
        }

        return intval(file_get_contents($pidFile));
    }

    /**
     * Rpc Server是否正在运行
     * 
     * @param int $pid
     * 
     * @return bool
     */
    protected function isRunning($pid)
    {
        if (!$pid) {
            return false;
        }

        try {
            return Process::kill($pid, 0);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Kill process.
     *
     * @param int $pid
     * @param int $sig
     * @param int $wait second
     *
     * @return bool
     */
    protected function killProcess($pid, $sig, $wait = 0)
    {
        Process::kill($pid, $sig);

        if ($wait) {
            $start = time();

            do {
                if (!$this->isRunning($pid)) {
                    break;
                }

                usleep(100000);
            } while (time() < $start + $wait);
        }

        return $this->isRunning($pid);
    }

    /**
     * 守护进程运行时输出日志
     */
    protected function consoleIfDaemon()
    {
        if (Config::get('app.server.0.settings.daemonize', 0)) {
            $this->info('> (You can run this command to ensure the RPC Server is running: ps aux|grep "rpc-server")');
        }
    }

    /**
     * 移除PID文件
     */
    protected function removePidFile()
    {
        $pidFile = $this->pidFile();
        if (is_file($pidFile)) {
            unlink($pidFile);
        }
    }
}
