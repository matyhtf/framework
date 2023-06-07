<?php

namespace SPF\Rpc\Tool;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use ReflectionProperty;
use SPF\Rpc\Config;

/**
 * 命名空间下类库反射表,在onWorkStart中初始化
 *
 * Class ReflectionClassMap
 * @package SPF\Tool
 */
class ReflectionClassMap
{
    public static $map = [];

    /**
     * @return array
     */
    public static function getMap()
    {
        return static::$map;
    }

    /**
     * @param string $implPath API的路径
     * @param string $implNsPrefix API的命名空间前缀，不包含 '\\' 结尾
     */
    public static function initMap($implPath = null, $implNsPrefix = null)
    {
        if (is_null($implPath)) {
            $implPath = Config::$rootPath . '/' . Config::get('app.tars.dstPath', 'src') . '/' .
                Config::get('app.tars.implDir', 'Impl');
        }
        if (is_null($implNsPrefix)) {
            $tarsNsPrefix = Config::get('app.tars.nsPrefix');
            $implNsPrefix = (is_null($tarsNsPrefix) ? Config::get('app.namespacePrefix') : $tarsNsPrefix) . '\\' .
                 Config::get('app.tars.implNs', 'Impl');
        }

        $implPath = realpath($implPath);
        $dirIterator = new RecursiveDirectoryIterator($implPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dirIterator);

        $map = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            
            // 获取完整类名，支持多级目录
            $absFilename = mb_substr($file, mb_strlen($implPath) + 1, -4);
            $className = $implNsPrefix . '\\' . str_replace('/', '\\', $absFilename);
            if (!class_exists($className)) {
                continue;
            }
            $refClass = new ReflectionClass($className);
            $cName = $refClass->name;

            // 方法的参数注释为了避免业务方修改，选择从interface中读取，参数验证从原类中读取
            foreach ($refClass->getInterfaces() as $refInterface) {
                foreach ($refInterface->getMethods(ReflectionMethod::IS_PUBLIC) as $refAbsMethod) {
                    $mName = $refAbsMethod->name;
                    $mParams = self::parseMethodDocParams($refAbsMethod->getDocComment());
                    // 从原方法中读取参数验证等Annodation
                    $refMethod = new ReflectionMethod($cName, $mName);
                    $mValids = self::parseMethodDocValidates($refMethod->getDocComment());
                    $map[$cName][$mName] = [
                        'params' => $mParams['params'],
                        'return' => $mParams['return'],
                        'validate' => $mValids,
                    ];
                }
            }
        }

        static::$map = $map;
    }

    /**
     * 解析注释中的参数
     *
     * @param string $doc
     *
     * @return array
     */
    protected static function parseMethodDocParams($doc)
    {
        $params = [];
        $return = [];
        foreach (explode("\n", $doc) as $line) {
            if (preg_match('/@(param|var)\s+([^\s]+)\s+\$([^\s]+)(\s+#(&)?([^\s]+)?)?/', $line, $matches) > 0) {
                // 参数注释
                $params[] = [
                    'name' => $matches[3],
                    'type' => empty($matches[6]) ? $matches[2] : $matches[6],
                    'proto' => empty($matches[6]) ? null : $matches[2],
                    'ref' => empty($matches[5]) ? false : $matches[5] === '&',
                    'index' => count($params),
                ];
            } elseif (preg_match('/@return\s+([^\s]+)(\s+#(&)?([^\s]+)?)?/', $line, $matches) > 0) {
                // 返回值注释
                $return = [
                    'type' => empty($matches[4]) ? $matches[1] : $matches[4],
                    'proto' => empty($matches[4]) ? null : $matches[1],
                    'ref' => empty($matches[3]) ? false : $matches[3] === '&',
                ];
            }
        }

        return compact('params', 'return');
    }

    /**
     * // TODO
     * 解析方法中文档参数注释
     * 必须包含 @param type? $fieldName {{rule1|rule2:param1|rule3:param2,param3}}
     *
     * @param string $doc 通过反射获取的文档注释 ReflectionMethod->getDocComment
     *
     * @return array
     */
    protected static function parseMethodDocValidates($doc)
    {
        $rules = [];
        // foreach(explode("\n", $doc) as $line) {
        //     if (preg_match('/@(param|var)(.*?)\$([^\s]+)(.*?)\{\{([^\}]+)\}\}/', $line, $matches) > 0) {
        //         $field = trim($matches[3]);
        //         $rule = [];
        //         foreach(explode('|', trim($matches[5])) as $rule_item) {
        //             $rule_parts = explode(':', trim($rule_item), 2);
        //             $params = isset($rule_parts[1]) ? explode(',', trim($rule_parts[1])) : [];
        //             $rule[trim($rule_parts[0])] = $params;
        //         }
        //         $rules[$field] = $rule;
        //     }
        // }

        return $rules;
    }
}
