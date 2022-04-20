<?php
namespace SPF\Exception;

/**
 * Class Syscall
 * @package SPF\Exception
 * @method static mkdir
 */
class Syscall extends \Exception
{
    static function __callStatic($func, $args)
    {
        if (call_user_func_array($func, $args) === false)
        {
            throw new self("$func(".implode(',', $args).") failed.");
        }
    }
}