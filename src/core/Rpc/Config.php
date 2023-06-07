<?php

namespace SPF\Rpc;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SPF\Exception\Exception;

/**
 * 配置统一管理
 * 使用前需要设置 Config::$rootPath 为项目绝对根路径
 */
class Config
{
    public static $config = [];

    public static $rootPath = null;

    /**
     * 根据路径载入
     * 自动将文件名作为顶层key
     *
     * @param string $path
     */
    public static function loadPath(string $path)
    {
        if (!is_dir($path)) {
            throw new Exception("config path [{$path}] is not exists");
        }

        $dirIterator = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dirIterator);
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $key = $file->getBasename('.php');
            static::$config[$key] = require_once $file;
        }
    }

    /**
     * 根据配置文件列表载入
     * 可以将数组的key作为配置顶层key
     *
     * @param array $list ['config1.php', 'mykey' => 'config2.php']
     */
    public static function loadList(array $list)
    {
        foreach ($list as $key => $file) {
            $filename = static::resolvePath($file);
            if (!is_file($filename)) {
                throw new Exception("config file [$file] is not exists");
            }
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'php') {
                throw new Exception("config file [$file] is not php file");
            }

            if (is_numeric($key)) {
                // 未设置key的根据文件设置
                $key = basename($filename, '.php');
            }
            static::$config[$key] = require_once $filename;
        }
    }

    /**
     * 获取配置
     *
     * @param string $key 配置键名，支持多层级，用.隔开，例如project.name
     * @param mixed $default 默认值
     *
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $config = static::$config;

        if (!$key) {
            return $config;
        }

        foreach (explode('.', $key) as $subKey) {
            if (isset($config[$subKey])) {
                $config = $config[$subKey];
                continue;
            }

            return $default;
        }

        return $config;
    }

    /**
     * 获取配置，配置不存在时抛出异常
     *
     * @param string $key 配置键名，支持多层级，用.隔开，例如project.name
     *
     * @return mixed
     */
    public static function getOrFailed($key)
    {
        $config = static::$config;

        if (!$key) {
            return $config;
        }

        foreach (explode('.', $key) as $subKey) {
            if (isset($config[$subKey])) {
                $config = $config[$subKey];
                continue;
            }

            throw new Exception("config {$key} not exists");
        }

        return $config;
    }

    /**
     * 设置配置
     *
     * @param string $key 配置键名，支持多层级，最多支持4层级，用.隔开，例如project.name
     * @param mixed $value 配置的值
     */
    public static function set($key, $value)
    {
        $s = explode('.', $key);
        switch (count($s)) {
            case 1:
                static::$config[$s[0]] = $value;
                break;
            case 2:
                static::$config[$s[0]][$s[1]] = $value;
                break;
            case 3:
                static::$config[$s[0]][$s[1]][$s[2]] = $value;
                break;
            case 4:
                static::$config[$s[0]][$s[1]][$s[2]][$s[3]] = $value;
                break;
            default:
                throw new Exception("config set max support 4 levels");
                break;
        }
    }

    /**
     * 移除配置
     *
     * @param string $key 配置键名，支持多层级，最多支持4层级，用.隔开，例如project.name
     */
    public static function remove($key)
    {
        $s = explode('.', $key);
        switch (count($s)) {
            case 1:
                if (isset(static::$config[$s[0]])) {
                    unset(static::$config[$s[0]]);
                }
                break;
            case 2:
                if (isset(static::$config[$s[0]]) && isset(static::$config[$s[0]][$s[1]])) {
                    unset(static::$config[$s[0]][$s[1]]);
                }
                break;
            case 3:
                if (
                    isset(static::$config[$s[0]]) && isset(static::$config[$s[0]][$s[1]]) &&
                    isset(static::$config[$s[0]][$s[1]][$s[2]])
                ) {
                    unset(static::$config[$s[0]][$s[1]][$s[2]]);
                }
                break;
            case 4:
                if (
                    isset(static::$config[$s[0]]) && isset(static::$config[$s[0]][$s[1]]) &&
                    isset(static::$config[$s[0]][$s[1]][$s[2]]) && isset(static::$config[$s[0]][$s[1]][$s[2]][$s[3]])
                ) {
                    unset(static::$config[$s[0]][$s[1]][$s[2]][$s[3]]);
                }
                break;
            default:
                throw new Exception("config remove max support 4 levels");
                break;
        }
    }

    /**
     * @param string $path
     *
     * @return string|bool
     */
    protected static function resolvePath($path)
    {
        if (strpos($path, '/') === 0) {
            // 绝对路径
            $realPath = realpath($path);
        } else {
            // 相对路径
            $realPath = realpath(static::$rootPath . '/' . $path);
        }

        if ($realPath === false) {
            return $realPath;
        }

        // 去掉目录后面的 / 分隔符
        if (strrpos($realPath, '/') === 0) {
            return mb_substr($realPath, 0, -1);
        }

        return $realPath;
    }

    /**
     * 是否处于debug模式
     *
     * @return bool
     */
    public static function debug()
    {
        return !!static::$config['app']['debug'];
    }
}
