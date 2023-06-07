<?php
namespace SPF\Command;

class MakeApplication
{
    public static function init($dir)
    {
        if (!is_dir($dir.'/controllers')) {
            mkdir($dir.'/controllers');
        }
        if (!is_dir($dir . '/configs')) {
            mkdir($dir . '/configs');
        }
        if (!is_dir($dir . '/models')) {
            mkdir($dir . '/models');
        }
        if (!is_dir($dir . '/classes')) {
            mkdir($dir . '/classes');
        }
        if (!is_dir($dir . '/events')) {
            mkdir($dir . '/events');
        }
        if (!is_dir($dir . '/templates')) {
            mkdir($dir . '/templates');
        }
        if (!is_dir($dir . '/factory')) {
            mkdir($dir . '/factory');
        }
    }
}
