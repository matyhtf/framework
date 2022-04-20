<?php
namespace SPF\IFace;

interface EventHandler
{
    function trigger($type, $data);
}