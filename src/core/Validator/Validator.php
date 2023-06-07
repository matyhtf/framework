<?php

namespace SPF\Validator;

use SPF\Exception\LogicException;
use SPF\Exception\ValidateException;

class Validator
{
    /**
     * User defined validate rules.
     *
     * @var array
     */
    protected static $rules = [];

    /**
     * User defined validate failed error messages.
     *
     * @var array
     */
    protected static $messages = [
        '*' => 'The %s argument must be %s',
    ];

    /**
     * Validate class with method, params map.
     *
     * @var array
     */
    protected static $validateMap = [];

    /**
     * Add new rule, event replace the framework rules.
     *
     * @param string $rule
     * @param callable $func function($attribute, $value, $params = [], $args = [])
     */
    public static function addRule(string $rule, callable $func)
    {
        $rule = static::replaceRuleName($rule);
        static::$rules[$rule] = $func;
    }

    /**
     * Add new rule, event replace the framework validate fail error messages.
     *
     * @param string $rule
     * @param callable $func function($field, $value, $params = [], $args = [])
     */
    public static function addMessage(string $rule, callable $func)
    {
        $rule = static::replaceRuleName($rule);
        static::$messages[$rule] = $func;
    }

    /**
     * Set the validate class with method, params.
     *
     * @param array $map
     */
    public static function setValidateMap(array $map)
    {
        static::$validateMap = $map;
    }

    /**
     * Get the validate class with method, params map.
     */
    public static function getValidateMap()
    {
        return static::$validateMap;
    }

    /**
     * Recursive get value from arguments with field join with dot(.)
     *
     * @param string $field such as object.user.userName
     * @param mixed $args
     * @param mixed $default
     *
     * @return mixed
     */
    public static function getValueFromArgs($field, $args, $default = null)
    {
        foreach (explode('.', $field) as $key) {
            if (is_scalar($args)) {
                return $default;
            } elseif (is_array($args)) {
                if (!isset($args[$key])) {
                    return $default;
                }
                $args = $args[$key];
            } elseif (is_object($args)) {
                if (!property_exists($args, $key)) {
                    return $default;
                }
                $args = $args->{$key};
            } else {
                return $default;
            }
        }

        return $args;
    }

    /**
     * Http 请求参数预校验和自动填充
     *
     * @param $class
     * @param $method
     * @param $args
     * @return array|bool
     */
    public static function validateHttpRequest($class, $method, $args)
    {
        $method = strtolower($method);
        $params = [];
        $map = Validator::getValidateMap();
        if (isset($map[$class]) && !empty($map[$class][$method])) {
            foreach ($map[$class][$method] as $param) {
                $field = strtolower($param['field']);
                if (isset($args[$field])) {
                    $params[] = $args[$field];
                } else {
                    /* 必选参数返回错误 */
                    if ($param['is_optional'] === false) {
                        return false;
                    }
                }
            }
        }
        return $params;
    }

    /**
     * @param $class
     * @param $method
     * @param $args
     * @throws LogicException
     * @throws ValidateException
     */
    public static function validateRequest($class, $method, $args)
    {
        if (empty($args)) {
            return ;
        }
        $method = strtolower($method);
        $map = Validator::getValidateMap();
        if (!isset($map[$class]) || empty($map[$class][$method])) {
            return ;
            // throw new ValidateException("no validation map", 404);
        }
        Validator::validate($args, $map[$class][$method]);
    }

    /**
     * @param $args
     * @param $argRules
     * @throws LogicException
     * @throws ValidateException
     */
    public static function validate($args, $argRules)
    {
        $errors = [];
        foreach ($args as $idx => $value) {
            if (!isset($argRules[$idx])) {
                continue;
            }
            $field = $argRules[$idx]['field'];
            $rules = $argRules[$idx]['rules'];
            static::validateFieldRules($rules, $field, $value, $args, $errors);

            // Validate extends rules
            static::validateFieldExtendsRules($argRules[$idx]['extends'] ?? null, $field.'.', $value, $args, $errors);
        }

        if (count($errors) > 0) {
            throw new ValidateException($errors);
        }
    }

    /**
     * Validate field rules.
     *
     * @param array $rules
     * @param string $field
     * @param mixed $value
     * @param array $args
     * @param array $errors
     */
    protected static function validateFieldRules($rules = [], $field = '', $value = null, $args = [], &$errors = [])
    {
        foreach ($rules as $rule => $params) {
            if (isset(static::$rules[$rule])) {
                // Use the user defined rules
                if (call_user_func(static::$rules[$rule], $rules, $value, $params, $args) === false) {
                    $errors[$field][$rule] = static::formatFailMessage($rule, $field, $value, $params, $args);
                }
            } elseif (method_exists(ValidateRules::class, 'validate' . ucfirst($rule))) {
                // Use the framework provided rules
                $callable = ValidateRules::class . '::validate' . ucfirst($rule);
                if (call_user_func($callable, $rules, $value, $params, $args) === false) {
                    $errors[$field][$rule] = static::formatFailMessage($rule, $field, $value, $params, $args);
                }
            } else {
                throw new LogicException("Validate rule [{$rule}] not found");
            }
        }
    }

    /**
     * Recurve Validate Field Extends Rules
     *
     * @param array $extends
     * @param string $fieldPrefix
     * @param mixed $value
     * @param array $args
     * @param array $errors
     */
    protected static function validateFieldExtendsRules($extends, $fieldPrefix = '', $value, $args, &$errors = [])
    {
        if (!empty($extends)) {
            foreach ($extends as $field => $argRules) {
                $rules = $argRules['rules'];
                $subValue = $value->{$field} ?? null;
                $fieldName = "{$fieldPrefix}{$field}";
                // if not set required rule and the value`s property is null, then continue
                if (!isset($rules['required']) && is_null($subValue)) {
                    continue;
                }

                static::validateFieldRules($rules, $fieldName, $subValue, $args, $errors);

                // Validate sub extends rules
                static::validateFieldExtendsRules($argRules['extends'] ?? null, $fieldName.'.', $subValue, $args, $errors);
            }
        }
    }

    /**
     * Replace validate rule from snake_case to camelCase.
     *
     * @param string $rule
     *
     * @return string
     */
    protected static function replaceRuleName($rule)
    {
        return preg_replace_callback('/_([a-z])/', function ($matches) {
            return strtoupper($matches[1]);
        }, $rule);
    }

    /**
     * Format validate fail error message.
     *
     * @param string $rule
     * @param string $field
     * @param mixed value
     * @param array $params
     * @param array $args
     *
     * @return string
     */
    protected static function formatFailMessage($rule, $field, $value, $params = [], $args = [])
    {
        if (isset(static::$messages[$rule])) {
            return call_user_func(static::$messages[$rule], $field, $value, $params, $args);
        } elseif (method_exists(ValidateRules::class, 'message' . ucfirst($rule))) {
            $callable = ValidateRules::class . '::message' . ucfirst($rule);
            return call_user_func($callable, $field, $value, $params, $args);
        } else {
            return sprintf(static::$messages['*'], $field, $rule);
        }
    }
}
