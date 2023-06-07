<?php
namespace SPF\Platform;

class Windows
{
    public function kill($pid, $signo)
    {
        return false;
    }

    public function fork()
    {
        return false;
    }
}
