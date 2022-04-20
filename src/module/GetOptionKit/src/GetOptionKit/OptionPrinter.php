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
use GetOptionKit\OptionSpecCollection;

class OptionPrinter implements OptionPrinterInterface
{
    public $specs;

    function __construct( OptionSpecCollection $specs)
    {
        $this->specs = $specs;
    }

    /**
     * render option descriptions
     *
     * @param integer $width column width
     * @return string output
     */
    function outputOptions($width = 40)
    {
        echo "* Available options:\n";
        $lines = array();
        foreach( $this->specs->all() as $spec )
        {
            $c1 = $spec->getReadableSpec();
            $line = sprintf("% {$width}s   %s", $c1, $spec->description);
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
        echo "\n";
    }
}
