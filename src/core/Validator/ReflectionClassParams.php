<?php

namespace SPF\Validator;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use RuntimeException;

/**
 *
 * Class ReflectionClassParams
 * @package SPF\Validator
 */
class ReflectionClassParams
{
    public $namespace;
    public $project_src;
    public $map = [];

    public static $isInited = false;
    public static $obj = null;


    public function __construct($namespace, $project_src)
    {
        if (empty($namespace) or empty($project_src)) {
            throw new RuntimeException("namespace or src can not be empty", 1);
        }

        if (!is_dir($project_src)) {
            throw new RuntimeException("invalid path, $project_src not exists ,", 2);
        }
        $this->namespace = $namespace;
        $this->project_src = $project_src;
    }

    public static function getInstance($namespace, $project_src)
    {
        if (!self::$obj) {
            self::$obj = new self($namespace, $project_src);
        }
        return self::$obj;
    }


    /**
     * 获取class method params详情的映射表
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getMap()
    {
        if (self::$isInited) {
            return $this->map;
        }
        $root = $this->project_src;
        $dir_iterator = new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir_iterator);

        $map = [];
        foreach ($iterator as $file) {
            $class_file = substr($file, strlen($root) + 1);
            $class_name = dirname($class_file) . '\\' . basename($class_file, '.php');
            $class_name = str_replace("/", "\\", $class_name);
            $ns_class_name = $this->namespace . "\\" . $class_name;
            $class = new ReflectionClass($ns_class_name);
            $methods = $class->getMethods();
            foreach ($methods as $method) {
                $method_name = strtolower($method->getName());
                $docValidateRules = $this->parseMethodDocValidates($method->getDocComment());

                foreach ($method->getParameters() as $idx => $param) {
                    $param_name = $param->getName();
                    $rules = $docValidateRules[$param_name] ?? [];
                    // if the param is not optional, then adding required rule into rules
                    if ($param->isOptional() === false) {
                        $rules['required'] = [];
                    }
                    $map[$ns_class_name][$method_name][$idx] = [
                        'field' => $param_name,
                        'type' => (string)$param->getType(),
                        'is_optional' => $param->isOptional(),
                        'rules' => $rules,
                    ];
                }
            }
        }
        self::$isInited = true;
        $this->map = $map;
        return $map;
    }

    /**
     * 解析方法中文档参数注释
     * 必须包含 @param type? $fieldName {{rule1|rule2:param1|rule3:param2,param3}}
     *
     * @param string $doc 通过反射获取的文档注释 ReflectionMethod->getDocComment
     *
     * @return array
     */
    protected function parseMethodDocValidates($doc)
    {
        $rules = [];
        foreach (explode("\n", $doc) as $line) {
            if (preg_match('/@param(.*?)\$([^\s]+)(.*?)\{\{([^\}]+)\}\}/', $line, $matches) > 0) {
                $field = trim($matches[2]);
                $rule = [];
                foreach (explode('|', trim($matches[4])) as $rule_item) {
                    $rule_parts = explode(':', trim($rule_item), 2);
                    $params = isset($rule_parts[1]) ? explode(',', trim($rule_parts[1])) : [];
                    $rule[trim($rule_parts[0])] = $params;
                }
                $rules[$field] = $rule;
            }
        }

        return $rules;
    }
}
