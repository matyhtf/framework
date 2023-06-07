<?php

namespace SPF\Validator;

class ValidateRules
{
    /**
     * @param array $attribute
     * @param mixed $value
     *
     * @return boolean
     */
    public static function validateIn($attribute, $value, $params = [])
    {
        return in_array($value, $params);
    }

    public static function messageIn($field, $value, $params = [])
    {
        $in = implode(',', $params);
        return "The {$field} argument must be in {$in}";
    }

    public static function validateNotIn($attribute, $value, $params = [])
    {
        return !in_array($value, $params);
    }

    public static function messageNotIn($field, $value, $params = [])
    {
        $in = implode(',', $params);
        return "The {$field} argument must not be in {$in}";
    }

    public static function validateEmail($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateBoolean($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) !== false;
    }

    public static function validateDomain($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_DOMAIN) !== false;
    }

    public static function validateFloat($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
    }

    public static function validateInt($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public static function validateIp($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    public static function validateUrl($attribute, $value)
    {
        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateNullable($attribute, $value)
    {
        return true;
    }

    public static function validateAlpha($attribute, $value)
    {
        return preg_match('/[a-zA-Z]+/', $value) > 0;
    }

    public static function messageAlpha($field, $value)
    {
        return "The {$field} argument must be [a-zA-Z]";
    }

    public static function validateAlphaNumeric($attribute, $value)
    {
        return preg_match('/[a-zA-Z0-9]+/', $value) > 0;
    }

    public static function messageAlphaNumeric($field, $value)
    {
        return "The {$field} argument must be [a-zA-Z0-9]";
    }

    public static function validateNumeric($attribute, $value)
    {
        return is_numeric($value);
    }

    public static function validateArray($attribute, $value)
    {
        return is_array($value);
    }

    public static function validateBetween($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size >= $params[0] && $size <= $params[1];
    }

    public static function messageBetween($field, $value, $params = [])
    {
        return "The {$field} argument must be between {$params[0]} and {$params[1]}";
    }

    public static function validateDate($attribute, $value)
    {
        if ((!is_string($value) && !is_array($value)) || strtotime($value) === false) {
            return false;
        }

        return true;
    }

    public static function validateDateFormat($attribute, $value, $params = [])
    {
        if (static::validateDate($attribute, $value) === false) {
            return false;
        }

        if (date($params[0], strtotime($value)) !== $value) {
            return false;
        }

        return true;
    }

    public static function messageDateFormat($field, $value, $params = [])
    {
        return "The {$field} argument must be date with format {$params[0]}";
    }

    // public static function validateDistinct($attribute, $value)
    // {
    //     // TODO
    //     return true;
    // }

    /**
     * Validate greate than >
     */
    public static function validateGt($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size > $params[0];
    }

    public static function messageGt($field, $value, $params = [])
    {
        return "The {$field} argument must be greate than {$params[0]}";
    }

    /**
     * Validate greate than Or equal >=
     */
    public static function validateGte($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size >= $params[0];
    }

    public static function messageGte($field, $value, $params = [])
    {
        return "The {$field} argument must be greate than or equal {$params[0]}";
    }

    /**
     * Validate less than <
     */
    public static function validateLt($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size < $params[0];
    }

    public static function messageLt($field, $value, $params = [])
    {
        return "The {$field} argument must be less than {$params[0]}";
    }

    /**
     * Validate less than Or equal <=
     */
    public static function validateLte($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size <= $params[0];
    }

    public static function messageLte($field, $value, $params = [])
    {
        return "The {$field} argument must be less than or equal {$params[0]}";
    }

    /**
     * Validate equal ==
     */
    public static function validateEq($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size == $params[0];
    }

    public static function messageEq($field, $value, $params = [])
    {
        return "The {$field} argument must be equal {$params[0]}";
    }

    public static function validateMax($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size <= $params[0];
    }

    public static function messageMax($field, $value, $params = [])
    {
        return "The {$field} argument`s max value is {$params[0]}";
    }

    public static function validateMin($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size >= $params[0];
    }

    public static function messageMin($field, $value, $params = [])
    {
        return "The {$field} argument`s min value is {$params[0]}";
    }

    public static function validateRegex($attribute, $value, $params = [])
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match($params[0], $value) > 0;
    }

    public static function messageRegex($field, $value, $params = [])
    {
        return "The {$field} argument must be matched by regex {$params[0]}";
    }

    public static function validateNotRegex($attribute, $value, $params = [])
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match($params[0], $value) < 1;
    }

    public static function messageNotRegex($field, $value, $params = [])
    {
        return "The {$field} argument must not be matched by regex {$params[0]}";
    }

    public static function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif (is_array($value) && count($value) < 1) {
            return false;
        }

        return true;
    }

    public static function validateSize($attribute, $value, $params = [])
    {
        $size = static::getSize($attribute, $value);

        return $size == $params[0];
    }

    public static function messageSize($field, $value, $params = [])
    {
        return "The {$field} argument`s size must be {$params[0]}";
    }

    public static function validateString($attribute, $value)
    {
        return is_string($value);
    }

    /**
     * Get the value`s size
     *
     * @param array $attribute
     * @param mixed $value
     *
     * @return int
     */
    protected static function getSize($attribute, $value)
    {
        if (is_numeric($value) && static::hasNumeric($attribute)) {
            return $value;
        } elseif (is_array($value)) {
            return count($value);
        } else {
            return mb_strlen($value);
        }
    }

    /**
     * Has numeric rule in setting rules
     *
     * @param array $attribute
     *
     * @return boolean
     */
    protected static function hasNumeric($attribute)
    {
        return isset($attribute['int']) || isset($attribute['numeric']) || isset($attribute['float']) || isset($attribute['uint']);
    }
}
