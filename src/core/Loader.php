<?php

namespace SPF;

use \ArrayObject;

/**
 * Swoole库加载器
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage base
 *
 */
class Loader
{
    /**
     * 命名空间的路径
     */
    protected $namespaces;
    protected $app;
    protected $objects;

    /**
     * Loader constructor.
     * @param $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->objects = array(
            'model' => new ArrayObject,
            'object' => new ArrayObject
        );
    }

    /**
     * 自动载入类
     * @param $class
     */
    public function autoload($class)
    {
        $root = explode('\\', trim($class, '\\'), 2);
        if (count($root) > 1 and isset($this->namespaces[$root[0]])) {
            include $this->namespaces[$root[0]] . '/' . str_replace('\\', '/', $root[1]) . '.php';
        }
    }

    /**
     * 设置根命名空间
     * @param $root
     * @param $path
     */
    public function addNameSpace($root, $path)
    {
        $this->namespaces[$root] = $path;
    }
}
