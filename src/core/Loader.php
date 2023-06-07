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
    /**
     * @var App
     */
    protected $app;
    protected $objects;

    protected static $appNameSpaces = [
        'model',
        'controller',
    ];

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
     * @throws Error
     */
    public function autoload($class)
    {
        $ns = explode('\\', ltrim($class, '\\'), 2);
        if (count($ns) === 0) {
            return;
        }
        $root = $ns[0];
        if (isset($this->namespaces[$root])) {
            include $this->namespaces[$root] . '/' . str_replace('\\', '/', $ns[1]) . '.php';
        } elseif (strcasecmp($root, 'app') === 0) {
            // controller, model 等应用的特殊命名空间
            foreach (self::$appNameSpaces as $name) {
                if (str_i_starts_with($ns[1], $name)) {
                    $class_name = ltrim(substr($ns[1], strlen($name)), '\\');
                    $class_file = $this->app->getPath() . '/' . $name . 's/' . str_replace('\\', '/', $class_name) . '.php';
                    if (is_file($class_file)) {
                        include $class_file;
                        return;
                    }
                }
            }
            // 其他
            $class_file = $this->app->getPath() . '/classes/' . str_replace('\\', '/', $ns[1]) . '.php';
            if (is_file($class_file)) {
                include $class_file;
            }
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
