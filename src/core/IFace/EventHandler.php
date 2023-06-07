<?php
namespace SPF\IFace;

interface EventHandler
{
    public function trigger($type, $data);
}
