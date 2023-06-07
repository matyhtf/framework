<?php
namespace SPF\Log;

use SPF;

class EchoLog extends SPF\Log implements SPF\IFace\Log
{
    protected $display = true;

    public function __construct($config)
    {
        if (isset($config['display']) and $config['display'] == false) {
            $this->display = false;
        }
        parent::__construct($config);
    }

    public function put($msg, $level = self::INFO)
    {
        if ($this->display) {
            $log = $this->format($msg, $level);
            if ($log) {
                echo $log;
            }
        }
    }
}
