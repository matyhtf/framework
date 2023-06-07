<?php
namespace SPF\Async;

/**
 * 通用的连接池框架
 * @package SPF\Async
 */
class Pool
{
    /**
     * 连接池的尺寸，最大连接数
     * @var int $poolSize
     */
    protected $poolSize;

    /**
     * idle connection
     * @var array $resourcePool
     */
    protected $resourcePool = array();

    protected $resourceNum = 0;
    protected $failureCount = 0;

    /**
     * @var \SplQueue
     */
    protected $idlePool;


    /**
     * @var \SplQueue
     */
    protected $taskQueue;

    protected $createFunction;

    protected $config;

    /**
     * @param int $poolSize
     * @param array $config
     * @throws \Exception
     */
    public function __construct($config = array(), $poolSize = 100)
    {
        $this->poolSize = $poolSize;
        $this->taskQueue = new \SplQueue();
        $this->idlePool = new \SplQueue();
        $this->config = $config;
    }

    /**
     * 加入到连接池中
     * @param $resource
     */
    public function join($resource)
    {
        //保存到空闲连接池中
        $this->resourcePool[spl_object_hash($resource)] = $resource;
        $this->release($resource);
    }

    /**
     * 失败计数
     */
    public function failure()
    {
        $this->resourceNum--;
        $this->failureCount++;
    }

    /**
     * @param $callback
     */
    public function create($callback)
    {
        $this->createFunction = $callback;
    }

    /**
     * 修改连接池尺寸
     * @param $newSize
     */
    public function setPoolSize($newSize)
    {
        $this->poolSize = $newSize;
    }

    /**
     * 移除资源
     * @param $resource
     * @return bool
     */
    public function remove($resource)
    {
        $rid = spl_object_hash($resource);
        if (!isset($this->resourcePool[$rid])) {
            return false;
        }
        //从resourcePool中删除
        unset($this->resourcePool[$rid]);
        $this->resourceNum--;
        return true;
    }

    /**
     * 请求资源
     * @param callable $callback
     * @return bool
     */
    public function request(callable $callback)
    {
        //入队列
        $this->taskQueue->enqueue($callback);
        //有可用资源
        if (count($this->idlePool) > 0) {
            $this->doTask();
        }
        //没有可用的资源, 创建新的连接
        elseif (count($this->resourcePool) < $this->poolSize and $this->resourceNum < $this->poolSize) {
            call_user_func($this->createFunction);
            $this->resourceNum++;
        }
    }

    /**
     * 释放资源
     * @param $resource
     */
    public function release($resource)
    {
        $this->idlePool->enqueue($resource);
        //有任务要做
        if (count($this->taskQueue) > 0) {
            $this->doTask();
        }
    }

    protected function doTask()
    {
        $resource = null;
        //从空闲队列中取出可用的资源
        while (count($this->idlePool) > 0) {
            $_resource = $this->idlePool->dequeue();
            $rid = spl_object_hash($_resource);
            //资源已经不可用了，连接已关闭
            if (!isset($this->resourcePool[$rid])) {
                continue;
            } else {
                //找到可用连接
                $resource = $_resource;
                break;
            }
        }
        //没有可用连接，继续等待
        if (!$resource) {
            if (count($this->resourcePool) == 0) {
                call_user_func($this->createFunction);
                $this->resourceNum++;
            }
            return;
        }
        $callback = $this->taskQueue->dequeue();
        call_user_func($callback, $resource);
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }
}
