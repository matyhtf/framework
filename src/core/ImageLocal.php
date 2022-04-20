<?php
namespace SPF;

class ImageLocal
{
    public $http_timeout = 10;
    protected $base_dir;
    protected $base_url;
    protected $referrer_url;

    public $beforeUrlGet;
    public $afterUrlGet;

    function __construct($basedir)
    {
        $this->base_dir = $basedir;
    }


    /**
     * 自动将给定的内容$data中远程图片的url改为本地图片，并自动将远程图片保存到本地
     * 指定最小尺寸，过滤小图片
     * @param $content
     * @param $from_url
     * @param $min_file_size
     * @return int
     */
    function execute(&$content, $from_url, $min_file_size = 0)
    {
        preg_match_all('~<img[^>]*(?<!_mce_)\s+src\s?=\s?([\'"])((?:(?!\1).)*?)\1[^>]*>~i', $content, $match);
        if (empty($match[2]))
        {
            return 0;
        }

        $image_n = 0;
        $replaced = array();
        $this->referrer_url = $from_url;

        foreach ($match[2] as $uri)
        {
            //已经替换过的
            if (isset($replaced[$uri]))
            {
                continue;
            }
            if(!(strpos($uri,"data:image") === false))
            {
                return false;
            }
            if ($this->beforeUrlGet)
            {
                $replaced_uri = call_user_func($this->beforeUrlGet, $this, $uri);
            }
            else
            {
                $replaced_uri = $uri;
            }
            $_abs_uri = str_replace("%","",HTML::parseRelativePath($from_url, $replaced_uri));
            $info = parse_url($_abs_uri);
            $path = $info['host'].'/'.ltrim($info['path'], '/');
            $file =  $this->base_dir.'/'.$path;

            $update = true;
            if (!is_file($file))
            {
                $dir = dirname($file);
                if (!is_dir($dir))
                {
                    mkdir($dir, 0777, true);
                }
                $update = $this->downloadFile($_abs_uri, $file, $min_file_size);
                if ($this->afterUrlGet)
                {
                    call_user_func($this->afterUrlGet, $this, $_abs_uri);
                }
            }
            if ($update)
            {
                $new_uri = $this->base_url .'/'. ltrim($path, '/');
                $content = str_replace($uri, $new_uri, $content);
                $replaced[$uri] = true;
                $image_n ++;
            }
            else
            {
                return false;
            }
        }
        return $image_n;
    }

    function downloadFile($url, $file, $min_file_size = 0)
    {
        $url = trim(html_entity_decode($url));
        $curl = new Client\CURL;
        if (!empty($this->referrer_url))
        {
            $curl->setReferrer($this->referrer_url);
        }
        $fp = fopen($file, 'w');
        if (!$fp)
        {
            return false;
        }
        return $curl->download($url, $fp, null, $this->http_timeout);
    }
}