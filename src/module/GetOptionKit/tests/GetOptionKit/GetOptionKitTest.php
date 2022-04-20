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
class GetOptionKitTest extends PHPUnit_Framework_TestCase 
{

    function testSpec()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );

        $opt->add( 'f|foo:' , 'option require value' );
        $opt->add( 'b|bar+' , 'option with multiple value' );
        $opt->add( 'z|zoo?' , 'option with optional value' );
        $opt->add( 'v|verbose' , 'verbose message' );
        $opt->add( 'd|debug'   , 'debug message' );

        $spec = $opt->get('foo');
        ok( $spec->isAttributeRequire() );

        $spec = $opt->get('bar');
        ok( $spec->isAttributeMultiple() );

        $spec = $opt->get('zoo');
        ok( $spec->isAttributeOptional() );

        $spec = $opt->get( 'debug' );
        ok( $spec );
        is_class( 'GetOptionKit\\OptionSpec', $spec );
        is( 'debug', $spec->long );
        is( 'd', $spec->short );
        ok( $spec->isAttributeFlag() );
    }

    function testRequire()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'f|foo:' , 'option require value' );
        $opt->add( 'b|bar+' , 'option with multiple value' );
        $opt->add( 'z|zoo?' , 'option with optional value' );
        $opt->add( 'v|verbose' , 'verbose message' );
        $opt->add( 'd|debug'   , 'debug message' );

        $firstExceptionRaised = false;
        $secondExceptionRaised = false;

        // option required a value should throw an exception
        try {
            $result = $opt->parse( array( '-f' , '-v' , '-d' ) );
        }
        catch (Exception $e) {
            $firstExceptionRaised = true;
        }

        // even if only one option presented in args array
        try {
            $result = $opt->parse( array( '-f' ) );
        }
        catch (Exception $e) {
            $secondExceptionRaised = true;
        }

        if ($firstExceptionRaised && $secondExceptionRaised) {
            return;
        }

        $this->fail('An expected exception has not been raised.');
    }

    function testMultiple()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'b|bar+' , 'option with multiple value' );
        $result = $opt->parse(explode(' ','-b 1 -b 2 --bar 3'));

        ok( $result->bar );
        count_ok(3,$result->bar);
    }

    function testIntegerTypeNonNumeric()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'b|bar:=i' , 'option with integer type' );

        $spec = $opt->get('bar');
        ok( $spec->isTypeInteger() );

        // test non numeric
        try {
            $result = $opt->parse(explode(' ','-b test'));
            ok( $result->bar );
        } catch ( GetOptionKit\NonNumericException $e ) {
            ok( $e );
            return;
        }
        $this->fail('An expected exception has not been raised.');
    }

    function testIntegerTypeNumeric()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'b|bar:=i' , 'option with integer type' );

        $spec = $opt->get('bar');
        ok( $spec->isTypeInteger() );

        $result = $opt->parse(explode(' ','-b 123123'));
        ok( $result->bar );
        ok( $result->bar === 123123 );
    }



    function testStringType()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'b|bar:=s' , 'option with type' );

        $spec = $opt->get('bar');
        ok( $spec->isTypeString() );

        $result = $opt->parse(explode(' ','-b text arg1 arg2 arg3'));
        ok( $result->bar );

        $args = $result->getArguments();
        ok( $args );
        count_ok(3,$args);
        is( 'arg1', $args[0] );
        is( 'arg2', $args[1] );
        is( 'arg3', $args[2] );
    }


    function testSpec2()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );
        $opt->add( 'long'   , 'long option name only.' );
        $opt->add( 'a'   , 'short option name only.' );
        $opt->add( 'b'   , 'short option name only.' );
        ok( $opt->specs->all() );
        ok( $opt->specs );
        ok( $opt->getSpecs() );
        ok( $result = $opt->parse(explode(' ','-a -b --long')) );
        ok( $result->a );
        ok( $result->b );
    }

    function testSpecCollection()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );

        $opt->add( 'f|foo:' , 'option requires a value.' );
        $opt->add( 'b|bar+' , 'option with multiple value.' );
        $opt->add( 'z|zoo?' , 'option with optional value.' );
        $opt->add( 'v|verbose' , 'verbose message.' );
        $opt->add( 'd|debug'   , 'debug message.' );
        $opt->add( 'long'   , 'long option name only.' );
        $opt->add( 's'   , 'short option name only.' );

        ok( $opt->specs->all() );
        ok( $opt->specs );
        ok( $opt->getSpecs() );

        count_ok( 7 , $array = $opt->specs->toArray() );
        ok( isset($array[0]['long'] ));
        ok( isset($array[0]['short'] ));
        ok( isset($array[0]['description'] ));

        ob_start();
        $opt->specs->printOptions();
        $content = ob_get_contents();
        ob_clean();
        like( '/option with/m', $content );

        # echo "\n".$content;
    }

    function test()
    {
        $opt = new \GetOptionKit\GetOptionKit;
        ok( $opt );

        $opt->add( 'f|foo:' , 'option require value' );
        $opt->add( 'b|bar+' , 'option with multiple value' );
        $opt->add( 'z|zoo?' , 'option with optional value' );
        $opt->add( 'v|verbose' , 'verbose message' );
        $opt->add( 'd|debug'   , 'debug message' );

        $result = $opt->parse( array( '-f' , 'foo value' , '-v' , '-d' ) );
        ok( $result );
        ok( $result->foo );
        ok( $result->verbose );
        ok( $result->debug );
        is( 'foo value', $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        $result = $opt->parse( array( '-f=foo value' , '-v' , '-d' ) );
        ok( $result );
        ok( $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        is( 'foo value', $result->foo );
        ok( $result->verbose );
        ok( $result->debug );

        $result = $opt->parse( array( '-vd' ) );
        ok( $result->verbose );
        ok( $result->debug );
    }


}
