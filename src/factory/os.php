<?php
if (PHP_OS == 'WINNT') {
    return new \SPF\Platform\Windows();
} else {
    return new \SPF\Platform\Linux();
}