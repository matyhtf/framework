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

class Argument 
{
    public $arg;

    function __construct($arg)
    {
        $this->arg = $arg;
    }


    function isLongOption()
    {
        return substr($this->arg,0,2) === '--';
    }

    function isShortOption()
    {
        return (substr($this->arg,0,1) === '-' ) 
            && (substr($this->arg,1,1) !== '-');
    }

    function isEmpty()
    {
        return empty($this->arg);
    }



    /**
     * check current argument is an option
     *
     *   -a
     *   --foo
     */
    function isOption()
    {
        return $this->isShortOption() || $this->isLongOption();
    }

    function getOptionName()
    {
        if( preg_match('/^[-]+([a-zA-Z0-9-]+)/',$this->arg,$regs) ) {
            return $regs[1];
        }
    }

    function containsOptionValue()
    {
        return preg_match('/=.+/',$this->arg);
    }

    function getOptionValue()
    {
        if( preg_match('/=(.+)/',$this->arg,$regs) ) {
            return $regs[1];
        }
    }

    /** 
     * check combined short flags
     *
     * like: -abc
     */
    function withExtraFlagOptions()
    {
        return preg_match('/^-[a-zA-Z0-9]{2,}/',$this->arg);
    }

    function extractExtraFlagOptions()
    {
        $args = array();
        for($i=2;$i< strlen($this->arg); ++$i) {
            $args[] = '-' . $this->arg[$i];
        }
        $this->arg = substr($this->arg,0,2); # -[a-z]
        return $args;
    }

    function __toString()
    {
        return $this->arg;
    }

}



