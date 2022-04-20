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
namespace GetOptionKit;
use ArrayAccess;
use Iterator;
use GetOptionKit\Argument;
use GetOptionKit\OptionSpec;


/**
 * Define the getopt parsing result
 *
 * create option result from array()
 *
 *     OptionResult::create($spec, array( 
 *         'key' => 'value'
 *     ), array( ... arguments ... ) );
 *
 */
class OptionResult 
    implements Iterator, ArrayAccess
{
    /**
     * @var array option specs, key => OptionSpec object 
     * */
    public $keys = array();

    private $currentKey;

    /* arguments */
    public $arguments = array();

    function __construct()
    {

    }

    function __isset($key)
    {
        return isset($this->keys[$key]);
    }

    function __get($key)
    {
        if( isset($this->keys[ $key ]) )
            return @$this->keys[ $key ]->value;
    }

    function __set($key,$value)
    {
        $this->keys[ $key ] = $value;
    }

    function has($key)
    {
        return isset($this->keys[ $key ]);
    }

    function set($key, OptionSpec $value)
    {
        $this->keys[ $key ] = $value;
    }

    function addArgument( Argument $arg)
    {
        $this->arguments[] = $arg;
    }

    function getArguments()
    {
        return array_map( function($e) { return $e->__toString(); }, $this->arguments );
    }


    /**
     * Iterator methods 
     */
    function rewind() 
    {
        return reset($this->keys);
    }

    function current() 
    {
        return current($this->keys)->value;
    }

    function key() 
    {
        return key($this->keys);
    }

    function next() 
    {
        return next($this->keys);
    }

    function valid() 
    {
        return key($this->keys) !== null;
    }


    public function offsetSet($name,$value)
    {
        $this->keys[ $name ] = $value;
    }
    
    public function offsetExists($name)
    {
        return isset($this->keys[ $name ]);
    }
    
    public function offsetGet($name)
    {
        return $this->keys[ $name ];
    }
    
    public function offsetUnset($name)
    {
        unset($this->keys[$name]);
    }
    
    public function toArray()
    {
        $array = array();
        foreach( $this->keys as $key => $option ) {
            $array[ $key ] = $option->value;
        }
        return $array;
    }

    static function create($specs,$values = array(),$arguments = null )
    {
        $new = new self;
        foreach( $specs as $spec ) {
            $id = $spec->getId();
            if( isset($values[ $id ]) ) {
                $new->$id = $spec;
                $spec->setValue( $values[$id] );
            }
            if( $arguments ) {
                foreach( $arguments as $arg ) {
                    $new->addArgument( new Argument( $arg ) );
                }
            }
        }
        return $new;
    }

}

