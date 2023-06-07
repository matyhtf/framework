<?php
namespace SPF;

use SPF\Exception\InvalidParam;

/**
 * Controller的基类，控制器基类
 */
class Controller extends BaseObject
{
    public $is_ajax = false;

    /**
     * 是否对GET/POST/REQUEST/COOKIE参数进行转义
     * @var bool
     */
    public $if_filter = true;

    protected $tpl_var = array();
    protected $template_dir;
    protected $traceInfo = array();
    protected $model;
    protected $config;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->model = $app->modelLoader;
        $this->config = $app->config;
        $this->template_dir = $app->app_path . '/templates/';
        if (!defined('TPL_PATH')) {
            define('TPL_PATH', $this->template_dir);
        }
        if ($this->if_filter) {
            Filter::request();
        }
        $app->__init();
    }

    /**
     * 跟踪信息
     * @param $title
     * @param $value
     */
    protected function trace($title, $value = '')
    {
        if (is_array($title)) {
            $this->traceInfo = array_merge($this->traceInfo, $title);
        } else {
            $this->traceInfo[$title] = $value;
        }
    }

    public function fetch($tpl_file ='')
    {
        ob_start();
        $this->display($tpl_file);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }

    public function value($array, $key, $default = '', $intval = false)
    {
        if (isset($array[$key])) {
            return $intval ? intval($array[$key]) : $array[$key];
        } else {
            return $default;
        }
    }

    /**
     * 输出JSON字串
     * @param string $data
     * @param int    $code
     * @param string $message
     *
     * @return string
     */
    public function json($data = '', $code = 0, $message = '')
    {
        $json = array('code' => $code, 'message' => $message, 'data' => $data);
        if (!empty($_REQUEST['jsonp'])) {
            $this->http->header('Content-type', 'application/x-javascript');
            return $_REQUEST['jsonp'] . "(" . json_encode($json) . ");";
        } else {
            $this->http->header('Content-type', 'application/json');
            return json_encode($json);
        }
    }

    public function message($code = 0, $msg = 'success')
    {
        $ret = array('code' => $code, 'message' => $msg);
        return $this->is_ajax ? $ret : json_encode($ret);
    }

    /**
     * @param $key
     * @param $value
     * @throws InvalidParam
     */
    public function assign($key, $value)
    {
        // Verify $key is reliable.
        if (strpos($key, '_swoole') === 0) {
            throw new InvalidParam("key is invalid.");
        }

        $this->tpl_var[$key] = $value;
    }

    /**
     * render template file, then display it.
     * @param string $tpl_file
     */
    public function display($tpl_file = '')
    {
        if (empty($tpl_file)) {
            $tpl_file = strtolower($this->app->env['mvc']['controller']) . '/' . strtolower($this->app->env['mvc']['view']) . '.php';
        }
        if (!is_file($this->template_dir . $tpl_file)) {
            Error::info('template error', "template file[" . $this->template_dir . $tpl_file . "] not found");
        }
        extract($this->tpl_var);
        include $this->template_dir . $tpl_file;
    }

    /**
     * 显示运行时间和内存占用
     *
     * @return string
     */
    protected function showTime()
    {
        $runtime = $this->app->runtime();
        // 显示运行时间
        $showTime = '执行时间: ' . $runtime['time'];
        // 显示内存占用
        $showTime .= ' | 内存占用:' . $runtime['memory'];
        return $showTime;
    }

    /**
     * 显示跟踪信息
     * @return string
     * @throws SessionException
     */
    public function showTrace()
    {
        $_trace =   array();
        $included_files = get_included_files();

        // 系统默认显示信息
        if (!empty($this->request->server['SCRIPT_NAME'])) {
            $_trace['请求脚本'] = $this->request->server['SCRIPT_NAME'];
        }

        $_trace['请求方法'] = $this->app->env['mvc']['controller'].'/'.$this->app->env['mvc']['view'];
        $_trace['USER_AGENT'] = $this->request->server['HTTP_USER_AGENT'];
        $_trace['HTTP版本'] = $this->request->server['SERVER_PROTOCOL'];
        $_trace['请求时间'] = date('Y-m-d H:i:s', $this->request->server['REQUEST_TIME']);

        if (isset($_SESSION)) {
            $_trace['SESSION_ID'] = session_id();
        }
        if ($this->app->db instanceof Database) {
            $_trace['读取数据库'] = $this->app->db->read_times . '次';
            $_trace['写入数据库'] = $this->app->db->write_times . '次';
        }
        $_trace['加载文件数目'] = count($included_files);
        $_trace['PHP执行占用'] = $this->showTime();
        $_trace = array_merge($this->traceInfo, $_trace);

        // 调用Trace页面模板
        $html = <<<HTMLS
<style type="text/css">
#swoole_trace_content  {
font-family:		Consolas, Courier New, Courier, monospace;
font-size:			14px;
background-color:	#fff;
margin:				40px;
color:				#000;
border:				#999 1px solid;
padding:			20px 20px 12px 20px;
}
</style>
	<div>
		<fieldset style="margin:5px;">
		<div style="overflow:auto;text-align:left;">
HTMLS;
        $html .= "<a href='".Tool::url_merge('_show_request', '1')."'>显示请求参数</a> |
        <a href='".Tool::url_merge('_show_session', '1')."'>显示会话信息</a> |
        <a href='".Tool::url_merge('_show_files', '1')."'>显示加载的PHP文件</a>
        <hr/>";
        foreach ($_trace as $key => $info) {
            $html .= $key . ' : ' . $info . BL;
        }
        if (!empty($this->request->get['_show_files'])) {
            //输出包含的文件
            $html .= '加载的文件：' . BL."<pre style='color: #666'>";
            foreach ($included_files as $file) {
                $html .= $file . "\n";
            }
            $html .= "</pre>";
        }
        $html .= "</div></fieldset>";
        $html .= "</div>";

        if (!empty($this->request->get['_show_request'])) {
            $output = '<fieldset style="margin:5px;"><div style="overflow:auto;text-align:left;">';
            $request = $this->app->request;
            $output .= "<h2>HEADER:</h2>".Tool::dump($request->header);
            $output .= "<h2>SERVER:</h2>".Tool::dump($request->server);
            if (!empty($request->files)) {
                $output .= "<h2>FILE:</h2>".Tool::dump($request->files);
            }
            if (!empty($request->cookie)) {
                $output .= "<h2>COOKIES:</h2>".Tool::dump($request->cookie);
            }
            if (!empty($request->get)) {
                $output .= "<h2>GET:</h2>".Tool::dump($this->app->request->get);
            }
            if (!empty($request->post)) {
                $output .= "<h2>POST:</h2>".Tool::dump($request->post);
            }

            $html .= $output."</div></fieldset>";
        }

        if (!empty($this->request->get['_show_session'])) {
            $output = '<fieldset style="margin:5px;"><div style="overflow:auto;text-align:left;">';
            $this->session->start();
            $output .= "<h2>SESSION:</h2>" . Tool::dump($this->request->session);
            $html .= $output."</div></fieldset>";
        }

        return $html;
    }

    /**
     * @param $data
     * @param $params
     * @throws InvalidParam
     */
    public function validate(&$data, $params)
    {
        foreach ($params as $k => $v) {
            if ($v instanceof \Closure) {
                $ret = call_user_func($v, $data[$k]);
                if ($ret === false) {
                    $e = new Exception\InvalidParam("verification failed.", InvalidParam::ERROR_USER_DEFINED);
                    $e->key = $k;
                    throw $e;
                } else {
                    continue;
                }
            }

            $s = swoole_string($v);
            $conds = $s->split('|');

            if ($conds->contains('required')) {
                if (!isset($data[$k])) {
                    $e = new Exception\InvalidParam("Parameter [$k] is required.", InvalidParam::ERROR_REQUIRED);
                    $e->key = $k;
                    throw $e;
                }
            } else {
                if (!isset($data[$k])) {
                    continue;
                }
            }

            if ($conds->contains('numeric') and !is_numeric($data[$k])) {
                type_incorrectly:
                $e = new Exception\InvalidParam("Parameter [$k] type is incorrectly.", InvalidParam::ERROR_TYPE_INCORRECTLY);
                $e->key = $k;
                throw $e;
            }
            //日期
            if ($conds->contains('date') and Validate::check('date', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //时间
            if ($conds->contains('time') and Validate::check('time', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //日期时间
            if ($conds->contains('datetime') and Validate::check('datetime', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //EMAIL
            if ($conds->contains('email') and Validate::check('email', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //mobile
            if ($conds->contains('mobile') and Validate::check('mobile', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //tel
            if ($conds->contains('tel') and Validate::check('tel', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //url
            if ($conds->contains('url') and Validate::check('url', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //version
            if ($conds->contains('version') and Validate::check('version', $data[$k]) === false) {
                goto type_incorrectly;
            }
            //整型
            if ($conds->contains('int')) {
                $data[$k] = intval($data[$k]);
            }
            //浮点型
            if ($conds->contains('float')) {
                $data[$k] = floatval($data[$k]);
            }
        }
    }

    public function __destruct()
    {
        $this->app->__clean();
    }
}
