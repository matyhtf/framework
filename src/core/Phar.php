<?php
namespace SPF;

class Phar
{
    public static function create($phar_file, $php_dir, $default_stub)
    {
        $phar = new \Phar($phar_file);
        $phar->buildFromDirectory($php_dir, '/\.php$/');
        $phar->compressFiles(\Phar::GZ);
        $phar->stopBuffering();
        $phar->setStub($phar->createDefaultStub($default_stub));
    }
}
