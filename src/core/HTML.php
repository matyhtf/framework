<?php
namespace SPF;
/**
 * HTML DOM处理器
 * 用于处理HTML的内容，提供类似于javascript DOM一样的操作
 * 例如getElementById getElementsByTagName createElement等
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage HTML
 *
 */
class HTML
{
    /**
     * 删除注释
     * @param $content
     * @return mixed
     */
    static function removeComment($content)
	{
	    return preg_replace('#<!--[^>]*-->#','',$content);
	}

    static function parseList(array $array, callable $callback, $class = '')
    {
        if ($class)
        {
            $html = "<ul class='$class'>";
        }
        else
        {
            $html = '<ul>';
        }

        foreach ($array as $k => $v)
        {
            $out = $callback($k, $v);
            $html .= '<li>' . $out . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * 解析相对路径
     * @param $current
     * @param $url
     * @return string
     */
    static function  parseRelativePath($current, $url)
    {
        //非HTTP开头的，相对路径
        if (substr($url, 0, 7) !== 'http://' and substr($url, 0, 8) !== 'https://' )
        {
            //以/开头的
            if ($url[0] == '/')
            {
                $_u = parse_url($current);
                return $_u['scheme'].'://'.$_u['host'].$url;
            }
            else
            {
                $n = strrpos($current, '/');
                return substr($current, 0, $n + 1).$url;
            }
        }
        else
        {
            return $url;
        }
    }

    /**
     * 删除HTML中的某些标签
     * @param $html
     * @param array $rules
     * @return mixed
     */
    static function  removeTag($html, $rules = array('script', 'style'))
    {
        //file_put_contents('tmp2.html', $html);
        foreach($rules as $r)
        {
            while(1)
            {
                $search1 = '<'.$r;
                $pos1 = stripos($html, $search1);
                if ($pos1 === false)
                {
                    break;
                }
                $search2 = '</'.$r.'>';
                $pos2 = stripos($html, $search2,$pos1);
                $offset = $pos2 + strlen($search2);

                //TODO 这里可能会是JS中又包含JS
                //if ($html[$offset] == '"' or $html[$offset] == "'")

                if ($pos2 === false)
                {
                    App::getInstance()->log->warn("未闭合的标签$r");
                    break;
                }
                $html = substr($html, 0, $pos1).substr($html, $offset);
            }
        }
        return $html;
    }

    /**
     * 删除HTML中的tag属性
     * @param $html
     * @param array $remove_attrs
     * @return mixed
     */
    static function removeAttr($html, $remove_attrs = array())
    {
        //删除所有属性
        if (!is_array($remove_attrs) or count($remove_attrs) == 0)
        {
            return preg_replace('~<([a-z]+)[^>]*>~i','<$1>', $html);
        }
        //删除部分指定的属性
        else
        {
            foreach($remove_attrs as $attr)
            {
                $regx = '~<([^>]*?)[\s\t\r\n]+('.$attr.'[\s\t\r\n]*=[\s\t\r\n]*([\"\'])[^\3]*?\3)([^>]*)>~i';
                $html = preg_replace($regx,'<$1 $4>', $html);
            }

            return $html;
        }
    }
}