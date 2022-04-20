GetOptionKit
============

A powerful GetOpt toolkit for PHP, which supports type constraints, flag,
multiple flag, multiple values, required value checking.

GetOptionKit is based on PHP5.3, with fine unit testing with PHPUnit
testing framework.

GetOptionKit is object-oriented, it's flexible and extendable.

## Features

- simple format.
- type constrant.
- multiple value, requried value, optional value checking.
- auto-generated help text from defined options.
- support app/subcommand option parsing.
- SPL library.

## Requirements

* PHP 5.3

## Install From Composer

```json
{
    "require": { 
        "corneltek/getoptionkit": "~1.2"
    }
}
```

## Install from PEAR

    pear channel-discover pear.corneltek.com
    pear install corneltek/GetOptionKit

## Supported formats

flags:

    program.php -a -b -c
    program.php -abc
    program.php -a -bc

with multiple values:

    program.php -a foo -a bar -a zoo -b -b -b

specify value with equal sign:

    program.php -a=foo
    program.php --long=foo

with normal arguments:

    program.php -a=foo -b=bar arg1 arg2 arg3
    program.php arg1 arg2 arg3 -a=foo -b=bar

## Option SPEC

    v|verbose    flag option (with boolean value true)
    d|dir:       option require a value (MUST require)
    d|dir+       option with multiple values.
    d|dir?       option with optional value
    dir:=s       option with type constraint of string
    dir:=string  option with type constraint of string
    dir:=i       option with type constraint of integer
    dir:=integer option with type constraint of integer
    d            single character only option
    dir          long option name


## Command Line Forms

    app [app-opts] [app arguments]

    app [app-opts] subcommand [subcommand-opts] [subcommand-args]

    app [app-opts] subcmd1 [subcmd-opts1] subcmd2 [subcmd-opts] subcmd3 [subcmd-opts3] [subcommand arguments....]

## Demo

Please check `examples/demo.php`.

Run:

    % php examples/demo.php -f test -b 123 -b 333

Print:

    * Available options:
          -f, --foo <value>    option requires a value.
         -b, --bar <value>+    option with multiple value.
        -z, --zoo [<value>]    option with optional value.
              -v, --verbose    verbose message.
                -d, --debug    debug message.
                     --long    long option name only.
                         -s    short option name only.
    Enabled options: 
    * key:foo      spec:-f, --foo <value>  desc:option requires a value.
        value => test

    * key:bar      spec:-b, --bar <value>+  desc:option with multiple value.
        Array
        (
            [0] => 123
            [1] => 333
        )

## Synopsis


```php
use GetOptionKit\GetOptionKit;

$getopt = new GetOptionKit;
$spec = $getopt->add( 'f|foo:' , 'option require value' );  # returns spec object.

$getopt->add( 'b|bar+' , 'option with multiple value' );
$getopt->add( 'z|zoo?' , 'option with optional value' );

$getopt->add( 'f|foo:=i' , 'option requires a integer value' );
$getopt->add( 'f|foo:=s' , 'option requires a string value' );

$getopt->add( 'v|verbose' , 'verbose flag' );
$getopt->add( 'd|debug'   , 'debug flag' );

$result = $getopt->parse( array( 'program' , '-f' , 'foo value' , '-v' , '-d' ) );
$result = $getopt->parse( $argv );

$result->verbose;
$result->debug;
```

More low-level usage:

```php
$specs = new OptionSpecCollection;
$spec_verbose = $specs->add('v|verbose');
$spec_color = $specs->add('c|color');
$spec_debug = $specs->add('d|debug');
$spec_verbose->description = 'verbose flag';

// ContinuousOptionParser
$parser = new ContinuousOptionParser( $specs );
$result = $parser->parse(explode(' ','program -v -d test -a -b -c subcommand -e -f -g subcommand2'));
$result2 = $parser->continueParse();
```

GetOptionKit\OptionPrinter can print options for you:

    * Available options:
                  -f, --foo   option requires a value.
                  -b, --bar   option with multiple value.
                  -z, --zoo   option with optional value.
              -v, --verbose   verbose message.
                -d, --debug   debug message.
                     --long   long option name only.
                         -s   short option name only.


## For Command-line application with subcommands

For application with subcommands is designed by following form:


    [app name] [app opts] 
                 [subcommand1] [subcommand-opts]
                 [subcommand2] [subcommand-opts]
                 [subcommand3] [subcommand-opts]
                 [arguments]

You can check the `tests/GetOptionKit/ContinuousOptionParserTest.php` unit test file:

```php
// subcommand stack
$subcommands = array('subcommand1','subcommand2','subcommand3');

// different command has its own options
$subcommand_specs = array(
    'subcommand1' => $cmdspecs,
    'subcommand2' => $cmdspecs,
    'subcommand3' => $cmdspecs,
);

// for saved options
$subcommand_options = array();

// command arguments
$arguments = array();

$argv = explode(' ','program -v -d -c subcommand1 -a -b -c subcommand2 -c subcommand3 arg1 arg2 arg3');

// parse application options first
$parser = new ContinuousOptionParser( $appspecs );
$app_options = $parser->parse( $argv );
while( ! $parser->isEnd() ) {
    if( $parser->getCurrentArgument() == $subcommands[0] ) {
        $parser->advance();
        $subcommand = array_shift( $subcommands );
        $parser->setSpecs( $subcommand_specs[$subcommand] );
        $subcommand_options[ $subcommand ] = $parser->continueParse();
    } else {
        $arguments[] = $parser->advance();
    }
}
```

## Todo

* Option Spec group.
* Named option value.
* Conflict option checking.
* option valid value checking.
* Custom command mapping.

## Command Line Utility Design Concept

* main program name should be easy to type, easy to remember.
* subcommand should be easy to type, easy to remember. length should be shorter than 7 characters.
* options should always have long descriptive name
* a program should be easy to check usage.

## General command interface

To list usage of all subcommands or the program itself:

	$ prog help

To list the subcommand usage

	$ prog help subcommand subcommand2 subcommand3

## Hacking

Fork this repository and clone it:

    $ git clone git://github.com/c9s/GetOptionKit.git
    $ cd GetOptionKit
    $ composer install --dev

Run PHPUnit to test:

    $ phpunit 


## License

MIT License


