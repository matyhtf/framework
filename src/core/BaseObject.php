<?php

namespace SPF;

/**
 * 所有Swoole应用类的基类
 * Class Ojbect
 *
 * @package SPF
 * @property Database $db
 * @property Client\CoMySQL $codb
 * @property IFace\Cache $cache
 * @property Upload $upload
 * @property Component\Event $event
 * @property Session $session
 * @property Template $tpl
 * @property \Redis $redis
 * @property \MongoClient $mongo
 * @property Config $config
 * @property Http\PWS $http
 * @property Log $log
 * @property Auth $user
 * @property URL $url
 * @property Limit $limit
 * @property Request $request
 * @property Response $response
 * @method Database              db
 * @method \MongoClient          mongo
 * @method \redis                redis
 * @method IFace\Cache           cache
 * @method URL                   url
 * @method Client\CoMySQL        codb
 * @method Platform\Linux os
 */
class BaseObject
{
    /**
     * @var App
     */
    protected $app;

    public function __get($key)
    {
        return $this->app->$key;
    }

    public function __call($func, $param)
    {
        return call_user_func_array(array($this->app, $func), $param);
    }
}
