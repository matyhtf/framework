<?php
namespace SPF\Protocol;

use SPF;

class AppServer extends HttpServer
{
    protected $router_function;
    protected $apps_path;

    public function onStart($serv, $worker_id = 0)
    {
        parent::onStart($serv, $worker_id);
        if (empty($this->apps_path)) {
            if (!empty($this->config['apps']['apps_path'])) {
                $this->apps_path = $this->config['apps']['apps_path'];
            } else {
                throw new AppServerException("AppServer require apps_path");
            }
        }
        $php = SPF\App::getInstance();
        $php->addHook(App::HOOK_CLEAN, function () {
            $php = SPF\App::getInstance();
            //模板初始化
            if (!empty($php->tpl)) {
                $php->tpl->clear_all_assign();
            }
        });
    }

    /**
     * 处理请求
     * @param SPF\Request $request
     * @return SPF\Response
     */
    public function onRequest(SPF\Request $request)
    {
        return SPF\App::getInstance()->handlerServer($request);
    }
}
