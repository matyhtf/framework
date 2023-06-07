<?php
namespace SPF;

/**
 * 分页类
 * 根据提供的数据，产生分页代码
 * @author Han Tianfeng
 * @package SwooleSystem
 * @subpackage HTML
 */
class Pager
{
    /**
     * config ,public
     */
    public $page_name = "page"; //page标签，用来控制url页。比如说xxx.php?PB_page=2中的PB_page
    public $next_page = '下一页'; //下一页
    public $pre_page = '上一页'; //上一页
    public $first_page = '首页'; //首页
    public $last_page = '尾页'; //尾页
    public $pre_bar = '上一分页条'; //上一分页条
    public $next_bar = '下一分页条'; //下一分页条
    public $format_left = '';
    public $format_right = '';
    public $page_tpl = '';

    public $fragment;
    public $span_open = array('first', 'last', 'next', 'previous');
    public $pagesize_group = array(10, 20, 50);
    public $span_class;
    /**
     * private
     *
     */
    public $pagebarnum = 10; //控制记录条的个数。
    public $totalpage = 0; //总页数
    public $pagesize = 10;
    public $total = 0;
    public $page = 1; //当前页
    public $offset = 0;
    public $style;

    /**
     * constructor构造函数
     *
     * @param array $array['total'],$array['perpage'],$array['nowindex'],$array['url']
     */
    public function __construct($array)
    {
        if (is_array($array)) {
            if (!isset($array['total'])) {
                Error::info(__FUNCTION__, 'need a param of total');
            }
            $total = intval($array['total']);
            /**
             * 兼容不同的Key写法
             */
            if (isset($array['pagesize'])) {
                $array['perpage'] = intval($array['pagesize']);
            }
            if (isset($array['page'])) {
                $array['nowindex'] = intval($array['page']);
            }
            //每页多少条
            $perpage = isset($array['perpage']) ? intval($array['perpage']) : 10;
            //当前页数
            $nowindex = isset($array['nowindex']) ? intval($array['nowindex']) : '';
            $url = isset($array['url']) ? $array['url'] : '';
        } else {
            $total = $array;
            $perpage = 10;
            $nowindex = '';
            $url = '';
        }
        if (!empty($array['page_name'])) {
            $this->set('page_name', $array['page_name']); //设置pagename
        }

        $this->pagesize = $perpage;
        $this->_set_nowindex($nowindex); //设置当前页
        $this->totalpage = ceil($total / $perpage);
        $this->total = $total;
        $this->offset = ($this->page - 1) * $perpage;
    }
    public function set_class($span, $classname)
    {
        $this->span_class[$span] = $classname;
    }

    /**
     * 设定类中指定变量名的值，如果改变量不属于这个类，将throw一个exception
     *
     * @param string $var
     * @param string $value
     */
    public function set($var, $value)
    {
        if (in_array($var, get_object_vars($this))) {
            $this->$var = $value;
        } else {
            Error::info(__FUNCTION__, $var . " does not belong to PB_Page!");
        }
    }

    /**
     * 获取显示"下一页"的代码
     * @return string
     */
    protected function next_page()
    {
        $style = @$this->span_class['next'];
        if ($this->page < $this->totalpage) {
            return $this->_get_link($this->_get_url($this->page + 1), $this->next_page, $style);
        }
        return '<span class="' . $style . '">' . $this->next_page . '</span>';
    }

    /**
     * 获取显示“上一页”的代码
     * @return string
     */
    protected function pre_page()
    {
        $style = @$this->span_class['previous'];
        if ($this->page > 1) {
            return $this->_get_link($this->_get_url($this->page - 1), $this->pre_page, $style);
        }

        return '<span class="' . $style . '">' . $this->pre_page . '</span>';
    }

    /**
     * 获取显示“首页”的代码
     * @return string
     */
    protected function first_page()
    {
        $style = @$this->span_class['first'];
        if ($this->page == 1) {
            return '<span class="' . $style . '">' . $this->first_page . '</span>';
        }

        return $this->_get_link($this->_get_url(1), $this->first_page, $style);
    }

    /**
     * 获取显示“尾页”的代码
     *
     * @return string
     */
    public function last_page()
    {
        $style = @$this->span_class['last'];
        if ($this->page == $this->totalpage) {
            return '<span class="' . $style . '">' . $this->last_page . '</span>';
        }
        return $this->totalpage ? $this->_get_link($this->_get_url($this->totalpage), $this->last_page, $style) : '<span>' . $this->last_page . '</span>';
    }

    protected function nowbar()
    {
        $style = $this->style;
        $plus = ceil($this->pagebarnum / 2);
        if ($this->pagebarnum - $plus + $this->page > $this->totalpage) {
            $plus = ($this->pagebarnum - $this->totalpage + $this->page);
        }
        $begin = $this->page - $plus + 1;
        $begin = ($begin >= 1) ? $begin : 1;
        $return = '';
        for ($i = $begin; $i < $begin + $this->pagebarnum; $i++) {
            if ($i <= $this->totalpage) {
                if ($i != $this->page) {
                    $return .= $this->_get_text($this->_get_link($this->_get_url($i), $i, $style));
                } else {
                    $return .= $this->_get_text('<span class="current">' . $i . '</span>');
                }
            } else {
                break;
            }
            $return .= "\n";
        }
        unset($begin);
        return $return;
    }

    /**
     * 获取mysql 语句中limit需要的值
     *
     * @return string
     */
    public function offset()
    {
        return $this->offset;
    }

    public function set_pagesize()
    {
        $str = '<div class="pagesize"><span>每页显示：</span>';
        foreach ($this->pagesize_group as $p) {
            if ($p == $this->pagesize) {
                $str .= "<span class='ps_cur' onclick='setPagesize($p)'>$p</span>";
            } else {
                $str .= "<span class='ps' onclick='setPagesize($p)'>$p</span>";
            }
        }
        return $str . '</div>';
    }

    /**
     * 控制分页显示风格（你可以增加相应的风格）
     *
     * @param int $mode
     * @return string
     */
    public function render($mode = null)
    {
        $pager_html = "<div class='pager'>";
        if ($mode === null) {
            if (in_array('first', $this->span_open)) {
                $pager_html .= $this->first_page();
            }
            if (in_array('previous', $this->span_open)) {
                $pager_html .= $this->pre_page();
            }
            $pager_html .= $this->nowbar();
            if (in_array('next', $this->span_open)) {
                $pager_html .= $this->next_page();
            }
            if (in_array('last', $this->span_open)) {
                $pager_html .= $this->last_page();
            }
            if (in_array('pagesize', $this->span_open)) {
                $pager_html .= $this->set_pagesize();
            }
            $pager_html .= '</div>';
            return $pager_html;
        }
        $pager_html .= '</div>';
        return $pager_html;
    }
    /*----------------private function (私有方法)-----------------------------------------------------------*/
    /**
     * 设置当前页面
     *
     */
    protected function _set_nowindex($nowindex)
    {
        if (empty($nowindex)) {
            //系统获取
            if (isset($_GET[$this->page_name])) {
                $this->page=intval($_GET[$this->page_name]);
            }
        } else {
            //手动设置
            $this->page=intval($nowindex);
        }
    }

    /**
     * 为指定的页面返回地址值
     * @param int $pageno
     * @return string $url
     */
    protected function _get_url($pageno = 1)
    {
        if (empty($this->page_tpl)) {
            return Tool::url_merge('page', $pageno, 'mvc,q');
        } else {
            return str_replace('{page}', $pageno, $this->page_tpl);
        }
    }

    /**
     * 获取分页显示文字，比如说默认情况下_get_text('<a href="">1</a>')将返回[<a href="">1</a>]
     *
     * @param String $str
     * @return string $url
     */
    protected function _get_text($str)
    {
        return $this->format_left . $str . $this->format_right;
    }

    /**
     * 获取链接地址
     */
    protected function _get_link($url, $text, $style = '')
    {
        $style = (empty($style)) ? '' : 'class="' . $style . '"';
        return '<a ' . $style . 'href="' . $url . '">' . $text . '</a>';
    }

    public function disable($what)
    {
        $arr = new ArrayObject($this->span_open);
        $arr->remove($what);
        $this->span_open = $arr->toArray();
    }
}
