<?php
/**
 * 此工具可以将css中的图片路径下载到本地
 */
if ($argc < 5)
{
    echo "php " . basename(__FILE__), " css文件目录 远程URL 目标文件夹 目标本地路径\n";
    exit;
}
$dir = $argv[1];
$remote = $argv[2];
$dest = $argv[3];
$csspath = $argv[4];

$files = scandir($dir);

foreach ($files as $f)
{
    if (substr($f, -4, 4) == '.css')
    {
        parseCssFile($dir . '/' . $f);
    }
}

function parseCssFile($f)
{
    global $remote, $dest, $csspath;
    $patten = '#url\s*\(\s*(.*)\s*\)#';
    $content = file_get_contents($f);
    $match = array();
    $n = preg_match_all($patten, $content, $match);
    if ($n > 0)
    {
        foreach ($match[1] as $_m)
        {
            $m = trim($_m, "'\" \r\n");
            if ($m[0] != '/')
            {
                $m = '/' . $m;
            }
            $_fd = $dest . $m;
            $_fs = $remote . $m;
            if (!is_dir(dirname($_fd)))
            {
                mkdir(dirname($_fd), 0777, true);
            }

            if (is_file($_fd))
            {
                echo "file exists.[$_fd]\n";
                continue;
            }
            str_replace($m, $csspath . $m, $content);
            if (copy($_fs, $_fd))
            {
                echo "download remote file[$_fs] to [$_fd].\n";
                //var_dump($m, $csspath.$m);
            }
            else
            {
                echo "download fail.file[$_fs] to [$_fd].\n";
            }
        }
    }
    file_put_contents($f, $content);
    echo "parse ok. [$f]\n";
}