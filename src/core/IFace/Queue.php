<?php
namespace SPF\IFace;

interface Queue
{
    function push($data);
    function pop();
}