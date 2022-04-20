<?php
namespace SPF;

/**
 * 通用视图类
 * 产生一个简单的请求控制，解析的结构，一般用于后台管理系统
 * 简单模拟List  delete  modify  add 4项操作
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage MVC
 */
class GeneralView
{
    /**
     * @var \SPF\App
     */
    protected $swoole;
    public $action = 'list';
    public $app_name;
    static public $method_prefix = 'admin';

    /**
     * @var Model
     */
    public $model;
    public $gets = array();

    //保存读取到的变量
    public $vars = array();
    static public $page_key = 'page';
    static public $pagesize_key = 'pagesize';
    static public $pagesize_default = 10;

    public $error_txt = '错误的参数';
    public $del_info = '删除成功！';
    public $set_info = '修改成功！';
    public $add_info = '增加成功！';
    public $success_info = '操作成功！';

    public $post_callback;
    public $add_callback;
    public $set_callback;
    public $del_callback;
    public $detail_callback;

    function __construct($swoole)
    {
        $this->swoole = $swoole;
    }

    function setParam($gets)
    {
        $this->gets = $gets;
    }

    function display($tpl = '')
    {
        if ($tpl)
        {
            $this->swoole->tpl->display($tpl);
        }
        else
        {
            $this->swoole->tpl->display(self::$method_prefix . '_' . $this->app_name . '_' . $this->action . '.html');
        }
    }

    function setModel($model_name)
    {
        $this->model = model($model_name);
    }

    function setTable($table_name)
    {
        $this->model = table($table_name);
    }

    function geturl($add = '')
    {
        return $_SERVER['PHP_SELF'] . '?action=' . $this->action . '&' . $add;
    }

    function run()
    {
        if (!empty($_GET['action']))
        {
            $this->action = $_GET['action'];
        }
        $this->action = Validate::word($this->action);

        $method = self::$method_prefix . '_' . $this->action;
        if (method_exists($this, $method))
        {
            call_user_func(array($this, $method));
        }
        else
        {
            Error::info('GeneralView Error!', "View <b>{$this->app_name}->{$method}</b> Not Found!");
        }
    }

    /**
     * 处理上传文件
     */
    function proc_upfiles()
    {
        if (!empty($_FILES))
        {
            foreach ($_FILES as $k => $f)
            {
                if (!empty($_FILES[$k]['type']))
                {
                    $_POST[$k] = $this->swoole->upload->save($k);
                }
            }
        }
    }

    /**
     * 处理删除请求
     */
    function action_del()
    {
        if (isset($_GET['del']))
        {
            $del = (int)$_GET['del'];
            $this->model->del($del);

            return true;
        }
        else
        {
            return false;
        }
    }

    function trim_post()
    {
        foreach ($_POST as &$val)
        {
            if (is_array($val))
            {
                $val = implode(',', $val);
            }
            $val = trim($val);
        }
    }

    /**
     * 过滤字段
     */
    private function filter_field()
    {
        foreach ($_POST as $k => $v)
        {
            if (strpos($this->gets['select'], $k) === false)
            {
                unset($_POST[$k]);
            }
        }
    }

    /**
     * 处理数据提交请求
     * @param $trim
     * @return bool
     */
    function action_post($trim = false)
    {
        if ($_POST)
        {
            $this->proc_upfiles();
            if ($trim)
            {
                $this->trim_post();
            }
            if ($this->post_callback)
            {
                call_user_func($this->post_callback, $this);
            }

            if (isset($this->gets['select']))
            {
                $this->filter_field();
            }
            if (isset($_GET['id']))
            {
                if ($this->gets['set_disable'])
                {
                    return false;
                }
                if ($this->set_callback)
                {
                    call_user_func($this->set_callback, $this);
                }
                $this->model->set((int)$_GET['id'], $_POST);
            }
            else
            {
                if ($this->gets['add_disable'])
                {
                    return false;
                }
                if ($this->add_callback)
                {
                    call_user_func($this->add_callback, $this);
                }
                $this->model->put($_POST);
            }

            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 处理详细内容请求
     * @return unknown_type
     */
    function action_detail()
    {
        if (isset($_GET['id']))
        {
            $this->vars['det'] = $this->model->get((int)$_GET['id'])->get();
            if ($this->detail_callback)
            {
                call_user_func($this->detail_callback, $this);
            }
            $this->swoole->tpl->assign('det', $this->vars['det']);

            return true;
        }

        return false;
    }

    /**
     * 处理内容列表请求
     * @return unknown_type
     */
    function action_list()
    {
        $_model = $this->model;
        $gets = $this->gets;
        $gets['page'] = empty($_REQUEST[self::$page_key]) ? 1 : $_REQUEST[self::$page_key];
        $gets['pagesize'] = empty($_REQUEST[self::$pagesize_key]) ? self::$pagesize_default : $_REQUEST[self::$pagesize_key];

        /**
         * @var Pager
         */
        $pager = null;
        $this->vars['list'] = $this->model->gets($gets, $pager);
        $this->vars['pager'] = array(
            'total' => $pager->total,
            'render' => $pager->render(),
            'pagesize' => $pager->pagesize,
            'totalpage' => $pager->totalpage,
            'nowpage' => $pager->page
        );
        $this->swoole->tpl->ref('pager', $this->vars['pager']);
        $this->swoole->tpl->ref('list', $this->vars['list']);
    }

    function handle_entity_op($config)
    {
        if (!isset($config['model']))
        {
            die('参数错误！');
        }
        $_model = model($config['model']);

        if ($_POST['job'] == 'push')
        {
            $digg = (int)$_POST['push'];
            $set['digest'] = $digg;
            $get['in'] = array('id', implode(',', $_POST['ids']));
            $_model->sets($set, $get);
            JS::js_parent_reload('推荐成功');
        }
    }

    function __get($key)
    {
        return $this->vars[$key];
    }

    function handle_entity_add($config)
    {
        if (!isset($config['model']))
        {
            die('参数错误！');
        }
        if (empty($config['tpl.add']))
        {
            $config['tpl.add'] = dirname(__DIR__) . '/data/tpl/admin_entity_add.html';
        }
        if (empty($config['tpl.modify']))
        {
            $config['tpl.modify'] = dirname(__DIR__) . '/data/tpl/admin_entity_modify.html';
        }

        $_model = model($config['model']);

        if ($_POST)
        {
            $this->proc_upfiles();
            if (!empty($_POST['id']))
            {
                //如果得到id，说明提交的是修改的操作
                $id = $_POST['id'];
                if ($_model->set($_POST['id'], $_POST))
                {
                    JS::js_back('修改成功', -2);
                    exit;
                }
                else
                {
                    JS::js_back('修改失败', -1);
                    exit;
                }
            }
            else
            {
                //如果没得到id，说明提交的是添加操作
                if (empty($_POST['title']))
                {
                    JS::js_back('标题不能为空！');
                    exit;
                }
                $id = $_model->put($_POST);
                JS::js_back('添加成功');
                exit;
            }
        }
        else
        {
            $this->swoole->plugin->load('fckeditor');
            if (isset($_GET['id']))
            {
                $id = $_GET['id'];
                $news = $_model->get($id)->get();
                $editor = editor("content", $news['content'], 480);
                $this->swoole->tpl->assign('editor', $editor);
                $this->swoole->tpl->assign('news', $news);
                $this->swoole->tpl->display($config['tpl.modify']);
            }
            else
            {
                $editor = editor("content", "", 480);
                $this->swoole->tpl->assign('editor', $editor);
                $this->swoole->tpl->display($config['tpl.add']);
            }
        }
    }

    function handle_entity_center($config)
    {
        if (!isset($config['model']) or !isset($config['name']))
        {
            die('参数错误！');
        }
        $_model = model($config['model']);
        $this->swoole->tpl->assign('act_name', $config['name']);
        if (empty($config['tpl.add']))
        {
            $config['tpl.add'] = dirname(__DIR__) . '/data/tpl/admin_entity_center_add.html';
        }
        if (empty($config['tpl.list']))
        {
            $config['tpl.list'] = dirname(__DIR__) . '/data/tpl/admin_entity_center_list.html';
        }
        if (isset($config['limit']) and $config['limit'] === true)
        {
            $this->swoole->tpl->assign('limit', true);
        }
        else
        {
            $this->swoole->tpl->assign('limit', false);
        }

        if (isset($_GET['add']))
        {
            if (!empty($_POST['name']))
            {
                $data['name'] = trim($_POST['name']);
                $data['pagename'] = trim($_POST['pagename']);
                $data['keywords'] = trim($_POST['keywords']);
                $data['fid'] = intval($_POST['fid']);
                $data['intro'] = trim($_POST['intro']);

                #增加
                if (empty($_POST['id']))
                {
                    unset($_POST['id']);
                    $_model->put($data);
                    JS::js_back('增加成功！');
                }
                #修改
                else
                {
                    $_model->set((int)$_POST['id'], $data);
                    JS::js_back('增加成功！');
                }
            }
            else
            {
                if (!empty($_GET['id']))
                {
                    $data = $_model->get((int)$_GET['id'])->get();
                    $this->swoole->tpl->assign('data', $data);
                }
                $this->swoole->tpl->display($config['tpl.add']);
            }
        }
        else
        {
            if (!empty($_GET['del']))
            {
                $del_id = intval($_GET['del']);
                $_model->del($del_id);
                JS::js_back('删除成功！');
            }
            //Error::dbd();
            $get['fid'] = empty($_GET['fid']) ? 0 : (int)$_GET['fid'];
            $get['page'] = empty($_GET['page']) ? 1 : (int)$_GET['page'];
            $get['pagesize'] = 15;
            $pager = null;
            $list = $_model->gets($get, $pager);
            $this->swoole->tpl->assign('list', $list);
            $this->swoole->tpl->assign('pager', array('total' => $pager->total, 'render' => $pager->render()));
            $this->swoole->tpl->display($config['tpl.list']);
        }
    }

    function handle_catelog_center($config)
    {
        if (!isset($config['model']) or !isset($config['name']))
        {
            die('参数错误！');
        }
        $_model = model($config['model']);
        $this->swoole->tpl->assign('act_name', $config['name']);
        if (empty($config['tpl.add']))
        {
            $config['tpl.add'] = dirname(__DIR__) . '/data/tpl/admin_catelog_center_add.html';
        }
        if (empty($config['tpl.list']))
        {
            $config['tpl.list'] = dirname(__DIR__) . '/data/tpl/admin_catelog_center_list.html';
        }
        if (isset($config['limit']) and $config['limit'] === true)
        {
            $this->swoole->tpl->assign('limit', true);
        }
        else
        {
            $this->swoole->tpl->assign('limit', false);
        }

        if (isset($_GET['add']))
        {
            if (!empty($_POST['name']))
            {
                $data['name'] = trim($_POST['name']);
                $data['pagename'] = trim($_POST['pagename']);
                $data['fid'] = intval($_POST['fid']);
                $data['intro'] = trim($_POST['intro']);
                $data['keywords'] = trim($_POST['keywords']);
                #增加
                if (empty($_POST['id']))
                {
                    $_model->put($data);
                    JS::js_back('增加成功！');
                }
                #修改
                else
                {
                    $_model->set((int)$_POST['id'], $data);
                    JS::js_back('修改成功！');
                }
            }
            else
            {
                if (!empty($_GET['id']))
                {
                    $data = $_model->get((int)$_GET['id'])->get();
                    $this->swoole->tpl->assign('data', $data);
                }
                $this->swoole->tpl->display($config['tpl.add']);
            }
        }
        else
        {
            if (!empty($_GET['del']))
            {
                $del_id = intval($_GET['del']);
                $_model->del($del_id);
                JS::js_back('删除成功！');
            }
            //Error::dbd();
            $get['fid'] = empty($_GET['fid']) ? 0 : (int)$_GET['fid'];
            $get['page'] = empty($_GET['page']) ? 1 : (int)$_GET['page'];
            $get['pagesize'] = 15;
            $pager = null;
            $list = $_model->gets($get, $pager);
            $this->swoole->tpl->assign('list', $list);
            $this->swoole->tpl->assign('pager', array('total' => $pager->total, 'render' => $pager->render()));
            $this->swoole->tpl->display($config['tpl.list']);
        }
    }

    function handle_attachment($config)
    {
        if (!isset($config['entity']) or !isset($config['attach']) or !isset($config['entity_id']))
        {
            die('参数错误！');
        }
        $_mm = model($config['entity']);
        $_ma = model($config['attach']);

        $this->swoole->tpl->assign('config', $config);
        if ($_POST)
        {
            $_ma->put($_POST);
        }
        if (isset($_GET['del']))
        {
            $dels['id'] = (int)$_GET['del'];
            $dels['aid'] = $config['entity_id'];
            $dels['limit'] = 1;
            $_ma->dels($dels);
        }
        $get['aid'] = $config['entity_id'];
        $get['pagesize'] = 16;
        $get['page'] = empty($get['page']) ? 1 : (int)$get['page'];
        $list = $_ma->gets($get, $pager);
        $this->swoole->tpl->assign('list', $list);
        $this->swoole->tpl->assign('pager', array('total' => $pager->total, 'render' => $pager->render()));
        if (empty($config['tpl.list']))
        {
            $config['tpl.list'] = dirname(__DIR__) . '/data/tpl/admin_attachment.html';
        }
        $this->swoole->tpl->display($config['tpl.list']);
    }
}
