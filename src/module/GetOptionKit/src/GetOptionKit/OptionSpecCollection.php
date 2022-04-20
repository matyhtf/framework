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
use GetOptionKit\OptionSpec;
use Iterator;

class OptionSpecCollection
    implements Iterator
{
    public $data = array();

    public $longOptions = array();
    public $shortOptions = array();

    function __construct()
    {
        $this->data = array();
    }

    function __clone()
    {
        foreach( $this->data as $k => $v ) {
            $this->data[ $k ] = clone $v;
        }
        foreach( $this->longOptions as $k => $v ) {
            $this->longOptions[ $k ] = clone $v;
        }
        foreach( $this->shortOptions as $k => $v ) {
            $this->shortOptions[ $k ] = clone $v;
        }
    }

    function add()
    {
        $num = func_num_args();
        $args = func_get_args();
        $first = $args[0];

        if( is_object($first) && is_a( $first , '\GetOptionKit\OptionSpec' ) ) {
            $this->addSpec( $first );
        }
        elseif( is_string( $first ) ) {
            $specString  = $args[0];
            $description = @$args[1];
            $key         = @$args[2];

            // parse spec string
            $spec = new OptionSpec($specString);
            if( $description )
                $spec->description = $description;
            if( $key )
                $spec->key = $key;
            $this->add( $spec );
            return $spec;
        }
        else {
            throw new Exception( 'Unknown Spec Type' );
        }
    }

    function addSpec( OptionSpec $spec )
    {
        $this->data[ $spec->getId() ] = $spec;
        if( $spec->long )
            $this->longOptions[ $spec->long ] = $spec;
        if( $spec->short )
            $this->shortOptions[ $spec->short ] = $spec;
        if( ! $spec->long && ! $spec->short )
            throw new Exception('Wrong option spec');
    }

    function getLongOption( $name )
    {
        return @$this->longOptions[ $name ];
    }

    function getShortOption( $name )
    {
        return @$this->shortOptions[ $name ];
    }

    /* get spec by spec id */
    function get($id)
    {
        return @$this->data[ $id ];
    }

    function getSpec($name)
    {
        if( isset($this->longOptions[ $name ] ))
            return $this->longOptions[ $name ];
        if( isset($this->shortOptions[ $name ] ))
            return $this->shortOptions[ $name ];
    }

    function size()
    {
        return count($this->data);
    }

    function all()
    {
        return $this->data;
    }

    function toArray()
    {
        $array = array();
        foreach($this->data as $k => $spec) {
            $item = array();
            if( $spec->long )
                $item['long'] = $spec->long;
            if( $spec->short )
                $item['short'] = $spec->short;
            $item['description'] = $spec->description;
            $array[] = $item;
        }
        return $array;
    }

    /**
     * render option descriptions
     *
     * @param integer $width column width
     * @return string output
     */
    function outputOptions($width = 24)
    {
        # echo "* Available options:\n";
        $lines = array();
        foreach( $this->all() as $spec )
        {
            $c1 = $spec->getReadableSpec();
            if( strlen($c1) > $width )
            {
                $line = sprintf("% {$width}s", $c1) . "\n" . $spec->description;
            }
            else
            {
                $line = sprintf("% {$width}s      %s",$c1, $spec->description );
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * print options descriptions to stdout
     */
    function printOptions($program = null)
    {
        if (empty($program))
        {
            global $argv;
            $program = "{$argv[0]}";
        }
        echo str_repeat("=", 80)."\nUsage: {$program}\n".str_repeat("=", 80)."\n";
        $lines = $this->outputOptions();
        echo join( "\n" , $lines );
        echo "\n\n";
    }

    /* iterator methods */
    public function rewind() 
    {
        return reset($this->data);
    }

    public function current() 
    {
        return current($this->data);
    }

    public function key() 
    {
        return key($this->data);
    }

    public function next() 
    {
        return next($this->data);
    }

    public function valid() 
    {
        return key($this->data) !== null;
    }

}
