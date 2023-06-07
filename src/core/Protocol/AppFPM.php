<?php
namespace SPF\Protocol;

use SPF;

require_once dirname(dirname(__DIR__)) . '/function/cli.php';

class AppFPM extends FastCGI
{
    protected $router_function;
    protected $apps_path;

    public function onStart($serv)
    {
        parent::onStart($serv);
        if (empty($this->apps_path)) {
            if (!empty($this->config['apps']['apps_path'])) {
                $this->apps_path = $this->config['apps']['apps_path'];
            } else {
                throw new \Exception(__CLASS__.": require apps_path");
            }
        }
        $php = SPF\App::getInstance();
        $php->addHook(SPF\App::HOOK_CLEAN, function () {
            $php = SPF\App::getInstance();
            //模板初始化
            if (!empty($php->tpl)) {
                $php->tpl->clear_all_assign();
            }
        });
    }

    public function onRequest(SPF\Request $request)
    {
        return SPF\App::getInstance()->handlerServer($request);
    }
}
