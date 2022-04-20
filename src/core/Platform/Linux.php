<?php
namespace SPF\Platform;

class Linux
{
    function kill($pid, $signo)
    {
        return posix_kill($pid, $signo);
    }

    function fork()
    {
        return pcntl_fork();
    }
}