<?php
/*
 * This file is part of the {{ }} package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace GetOptionKit;
use GetOptionKit\NonNumericException;

class OptionSpec 
{
    public $short;
    public $long;
    public $description; /* description */
    public $key;  /* key to store values */
    public $value;
    public $type;

    public $valueName; /* name for the value place holder, for printing */

    const attr_multiple = 1;
    const attr_optional = 2;
    const attr_require  = 4;
    const attr_flag     = 8;

    const type_string   = 1;
    const type_integer  = 2;

    function __construct($specString = null)
    {
        if( $specString ) {
            $this->initFromSpecString($specString);
        }
    }

    /* 
     * build spec attributes from spec string 
     *
     **/
    function initFromSpecString($specString)
    {
        $pattern = '/
        (
                (?:[a-zA-Z0-9-]+)
                (?:
                    \|
                    (?:[a-zA-Z0-9-]+)
                )?
        )
        ([:+?])?
        (?:=([si]|string|integer))?
        /x';

        if( preg_match( $pattern, $specString , $regs ) === false ) {
            throw new Exception( "Unknown spec string" );
        }

        $orig       = $regs[0];
        $name       = $regs[1];
        $attributes = @$regs[2];
        $type       = @$regs[3];

        $short = null;
        $long = null;

        // check long,short option name.
        if( strpos($name,'|') !== false ) {
            list($short,$long) = explode('|',$name);
        } elseif( strlen($name) === 1 ) {
            $short = $name;
        } elseif( strlen($name) > 1 ) {
            $long = $name;
        }

        $this->short  = $short;
        $this->long   = $long;

        // option is required.
        if( strpos($attributes,':') !== false ) {
            $this->setAttributeRequire();
        }
        // option with multiple value
        elseif( strpos($attributes,'+') !== false ) {
            $this->setAttributeMultiple();
        }
        // option is optional.(zero or one value)
        elseif( strpos($attributes,'?') !== false ) {
            $this->setAttributeOptional();
        } 

        // option is multiple value and optional (zero or more)
        elseif( strpos($attributes,'*') !== false ) {
            throw new Exception('not implemented yet');
        }
        // is a flag option
        else {
            $this->setAttributeFlag();
        }

        if( $type ) {
            if( $type === 's' || $type === 'string' ) {
                $this->setTypeString();
            }
            elseif( $type === 'i' || $type === 'integer' ) {
                $this->setTypeInteger();
            }
        }
    }


    /*
     * get the option key for result key mapping.
     */
    function getId()
    {
        if( $this->key )
            return $this->key;
        if( $this->long )
            return $this->long;
        if( $this->short )
            return $this->short;
    }

    function setAttributeRequire()
    {
        $this->attributes = self::attr_require;
    }

    function setAttributeMultiple()
    {
        $this->attributes = self::attr_multiple;
        $this->value = array();  # for value pushing
    }

    function setAttributeOptional()
    {
        $this->attributes = self::attr_optional;
    }

    function setAttributeFlag()
    {
        $this->attributes = self::attr_flag;
    }


    function isAttributeFlag()
    {
        return $this->attributes & self::attr_flag;
    }

    function isAttributeMultiple()
    {
        return $this->attributes & self::attr_multiple;
    }

    function isAttributeRequire()
    {
        return $this->attributes & self::attr_require;
    }

    function isAttributeOptional()
    {
        return $this->attributes & self::attr_optional;
    }


    function setTypeString()
    {
        $this->type = self::type_string;
    }

    function setTypeInteger()
    {
        $this->type = self::type_integer;
    }

    function isTypeString()
    {
        return $this->type & self::type_string;
    }

    function isTypeInteger()
    {
        return $this->type & self::type_integer;
    }

    /*
     * check value constraint type
     * current for integer and string.
     */
    function checkType($value)
    {
        if( $this->type !== null ) {
            // check type constraints
            if( $this->isTypeInteger() ) {
                if( ! is_numeric($value) )
                    throw new NonNumericException;
                $value = (int) $value;
            }
        }
        return $value;
    }

    /*
     * set option value
     */
    function setValue($value)
    {
        $value = $this->checkType($value);
        $this->value = $value;
    }


    /*
     * push option value, when the option accept multiple values 
     */
    function pushValue($value)
    {
        $value = $this->checkType($value);
        $this->value[] = $value;
    }

    function setDescription($desc)
    {
        $this->description = $desc;
    }

    function setValueName($name)
    {
        $this->valueName = $name;
    }


    /*
     * set option spec key for saving option result
     */
    function setKey($key)
    {
        $this->key = $key;
    }

    /*
     * get readable spec for printing
     *
     */
    function getReadableSpec()
    {
        $c1 = '';
        if( $this->short && $this->long )
            $c1 = sprintf('-%s, --%s',$this->short,$this->long);
        elseif( $this->short )
            $c1 = sprintf('-%s',$this->short);
        elseif( $this->long )
            $c1 = sprintf('--%s',$this->long );

        $valueName = 'value';
        if( $this->valueName )
            $valueName = $this->valueName;

        if( $this->isAttributeRequire() ) {
            $c1 .= " <$valueName>";
        }
        elseif( $this->isAttributeMultiple() ) {
            $c1 .= " <$valueName>+"; // better expression
        }
        elseif( $this->isAttributeOptional() ) {
            $c1 .= " [<$valueName>]";
        }
        elseif( $this->isAttributeFlag() ) {

        }
        return $c1;
    }

    function validate()
    {
        // validate current value
    }

    function __toString()
    {
        $c1 = $this->getReadableSpec();
        $return = '';
        $return .= sprintf("* key:%-8s spec:%s  desc:%s",$this->getId(), $c1,$this->description) . "\n";
        if( is_array($this->value) ) {
            $return .= '  ' . print_r(  $this->value, true ) . "\n";
        } else {
            $return .= sprintf("  value => %s" , $this->value) . "\n";
        }
        return $return;
    }

}


