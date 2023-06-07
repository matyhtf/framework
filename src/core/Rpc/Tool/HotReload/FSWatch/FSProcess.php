<?php

namespace SPF\Rpc\Tool\HotReload\FSWatch;

use SPF\Rpc\Tool\HotReload\HotReloadable;
use Symfony\Component\Process\Process as AppProcess;

/**
 * Class FSProcess
 */
class FSProcess implements HotReloadable
{
    /**
     * 回调
     *
     * @var callable|string|array
     */
    protected $callback;

    /**
     * When locked event cannot do anything.
     *
     * @var bool
     */
    protected $locked;

    /**
     * Fswatch watch file event types
     *
     * @var array
     */
    protected $eventTypes = ['Created', 'Updated', 'Removed', 'Renamed'];
    
    /**
     * 根路径
     *
     * @var string
     */
    protected $rootPath;

    /**
     * @var array
     */
    protected $config = [];

    public function __construct($rootPath, array $config = [])
    {
        $this->rootPath = $rootPath;
        $this->config = array_merge($this->config, $config);
    }

    /**
     * @return \swoole_process
     */
    public function make()
    {
        $mcb = function ($type, $buffer) {
            $logs = FSEventParser::toEvent($buffer, $this->eventTypes);
            if (! $this->locked && $logs) {
                $this->locked = true;
                call_user_func($this->callback, $logs);
                $this->locked = false;
            }
        };

        return new \swoole_process(function () use ($mcb) {
            (new AppProcess($this->configure()))->setTimeout(0)->run($mcb);
        }, false, false);
    }

    /**
     * @param callable|string|array $callback
     *
     * @return self
     */
    public function setCallback($callback)
    {
        if (is_string($callback)) {
            if (strpos($callback, '::') > 0) {
                list($class, $method) = explode('::', $callback);
                if (!method_exists($class, $method)) {
                    throw new Exception("Inotify watch callback invalid");
                }
            } elseif (!function_exists($callback)) {
                throw new Exception("Inotify watch callback invalid");
            }

            $this->callback = $callback;
        } elseif (is_array($callback)) {
            if (!method_exists($callback[0], $callback[1])) {
                throw new Exception("Inotify watch callback invalid");
            }

            $this->callback = $callback;
        } elseif (is_callable($callback)) {
            $this->callback = $callback;
        } else {
            throw new Exception("Inotify watch callback invalid");
        }
    }

    /**
     * Configure process.
     *
     * @return array
     */
    protected function configure(): array
    {
        $configure = [
            'fswatch',
            $this->config['fswatch']['recursively'] ? '-rtx' : '-tx',
            '--format-time',
            '%Y-%m-%d %H:%M:%S',
        ];

        foreach ($this->config['fswatch']['watchOptions'] as $option) {
            foreach ((array)$option as $opt) {
                $configure[] = $opt;
            }
        }

        $configure[] = $this->rootPath;

        return $configure;
    }
}
