<?php

namespace SPF\Rpc\Tool\HotReload\Inotify;

use SPF\Exception\Exception;
use SPF\Rpc\Tool\HotReload\HotReloadable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InotifyProcess implements HotReloadable
{
    /**
     * 回调
     *
     * @var callable|string|array
     */
    protected $callback;

    /**
     * @var array
     */
    protected $config = [];

    /**
     * Inotify文件资源
     *
     * @var resource
     */
    protected $fd = null;

    /**
     * 监控相对的根路径，用于处理相对路径参照
     *
     * @var string
     */
    protected $rootPath;

    /**
     * 监控的inotify wd资源
     *
     * @var array
     */
    protected $watches = [];

    /**
     * 是否已上锁
     *
     * @var bool
     */
    protected $locked = false;

    public function __construct($rootPath, array $config = [])
    {
        $this->rootPath = $rootPath;
        $this->config = array_merge($this->config, $config);

        $this->checkRequire();
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
     * @return \swoole_process
     */
    public function make()
    {
        return new \swoole_process(function ($worker) {
            $this->init();
            $this->addWatches();
            $this->handleWatch();
        });
    }

    protected function handleWatch()
    {
        while (true) {
            if (!$this->locked && $events = inotify_read($this->fd)) {
                $this->locked = true;

                $logs = [];
                $date = date('Y-m-d H:i:s');
                foreach ($events as $event) {
                    $resourceType = 'File';
                    switch ($event['mask']) {
                        case IN_CREATE:
                            $eventType = 'Created';
                            break;
                        case IN_MODIFY:
                            $eventType = 'Updated';
                            break;
                        case IN_DELETE:
                            $eventType = 'Deleted';
                            break;
                        case IN_MOVE:
                            $eventType = 'Renamed';
                            break;
                        case IN_MOVED_FROM:
                            $eventType = 'Renamed To NewName';
                            break;
                        case IN_MOVED_TO:
                            $eventType = 'Renamed From OldName';
                            break;
                        case IN_IGNORED:
                            $resourceType = 'Directory';
                            $eventType = 'Reload Watched';
                            break;
                        case '1073742080':
                            $resourceType = 'Directory';
                            $eventType = 'Created';
                            $this->reloadWatch($event['wd']);
                            break;
                        case '1073742336':
                            $resourceType = 'Directory';
                            $eventType = 'Deleted';
                            $this->removeWatch($event['wd']);
                            break;
                        case '1073741888':
                            $resourceType = 'Directory';
                            $eventType = 'Renamed To NewName';
                            $this->reloadParentWatch($event['wd']);
                            break;
                        case '1073741952':
                            $resourceType = 'Directory';
                            $eventType = 'Renamed From OldName';
                            break;
                        default:
                            $eventType = 'Unknown';
                            break;
                    }
                    $logs[] = "{$resourceType}: {$event['name']} {$eventType} at {$date}";
                }

                call_user_func($this->callback, $logs);

                $this->locked = false;
            }
            
            sleep(1);
        }
    }

    /**
     * 初始化添加监控
     */
    protected function addWatches()
    {
        foreach ($this->config['inotify']['watchOptions'] as $path) {
            $this->addWatch($path);
        }
    }

    /**
     * 添加监控
     *
     * @param string $path
     */
    protected function addWatch($path)
    {
        $this->watches[] = inotify_add_watch($this->fd, $path, IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVE);

        // 目录类型递归添加监控
        if (is_dir($path)) {
            $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
            $dirInterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($dirInterator as $file) {
                if (!$file->isDir()) {
                    continue;
                }

                $wd = inotify_add_watch($this->fd, $file, IN_MODIFY | IN_CREATE | IN_DELETE | IN_MOVE);

                $this->watches[$wd] = $file;
            }
        }
    }

    /**
     * 移除监控，该文件夹被删除
     *
     * @param int $wd
     */
    protected function removeWatch($wd)
    {
        @inotify_rm_watch($this->fd, $wd);
        unset($this->watches[$wd]);
    }

    /**
     * 重启监控的文件，该文件夹创建了子文件夹，重启添加子文件夹监控
     *
     * @param int $wd
     */
    protected function reloadWatch($wd)
    {
        @inotify_rm_watch($this->fd, $wd);
        $this->addWatch($this->watches[$wd]);
        unset($this->watches[$wd]);
    }

    /**
     * 重启监控的父文件，用于文件夹重命名重启监控
     *
     * @param int $wd
     */
    protected function reloadParentWatch($wd)
    {
        $dir = dirname($this->watches[$wd]);
        foreach ($this->watches as $curWd => $path) {
            if (strpos($path, $dir) === 0) {
                $this->removeWatch($curWd);
            }
        }
        $this->addWatch($dir);
    }

    /**
     * 检查运行环境
     */
    protected function checkRequire()
    {
        if (!extension_loaded('inotify')) {
            throw new Exception("Please install php extension inotify, for detail see https://www.php.net/manual/zh/book.inotify.php");
        }
    }

    /**
     * 初始化inotify资源
     */
    protected function init()
    {
        $this->fd = inotify_init();

        // 将监控路径全部转为绝对路径
        foreach ($this->config['inotify']['watchOptions'] as &$item) {
            if (strpos($item, '/') !== 0) {
                $item = $this->rootPath . '/' . $item;
            }
        }
        unset($item);
    }
}
