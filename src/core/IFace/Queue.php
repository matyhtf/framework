<?php
namespace SPF\IFace;

interface Queue
{
    public function push($data);
    public function pop();
}
