#!/usr/bin/env php
<?php
/*
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
require 'Universal/ClassLoader/SplClassLoader.php';
$classLoader = new Universal\ClassLoader\SplClassLoader(array( 'GetOptionKit' => 'src' ));
$classLoader->register();


use GetOptionKit\GetOptionKit;
$opt = new GetOptionKit;
$opt->add( 'f|foo:' , 'option requires a value.' );
$opt->add( 'b|bar+' , 'option with multiple value.' );
$opt->add( 'z|zoo?' , 'option with optional value.' );
$opt->add( 'v|verbose' , 'verbose message.' );
$opt->add( 'd|debug'   , 'debug message.' );
$opt->add( 'long'   , 'long option name only.' );
$opt->add( 's'   , 'short option name only.' );
$opt->specs->printOptions();


echo "Enabled options: \n";
try {
    $result = $opt->parse( $argv );
    foreach( $result as $key => $spec ) {
        echo $spec . "\n";
    }
} catch( Exception $e ) {
    echo $e->getMessage();
}
