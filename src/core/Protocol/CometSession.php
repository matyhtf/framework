<?php

namespace SPF\Protocol;

use SPF;

class CometSession extends \SplQueue
{
    public $id;
    /**
     * @var \SplQueue
     */
    protected $msg_queue;

    public function __construct()
    {
        $this->id = md5(uniqid('', true));
    }

    public function getMessageCount()
    {
        return count($this);
    }

    public function pushMessage($msg)
    {
        return $this->enqueue($msg);
    }

    public function popMessage()
    {
        return $this->dequeue();
    }
}
