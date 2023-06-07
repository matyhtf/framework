<?php

namespace SPF;

use SPF\Exception\NotFound;

/**
 * Class Factory
 * @package SPF
 * @method static getCache
 */
class Factory
{
    /**
     * @throws NotFound
     */
    public static function __callStatic($func, $params)
    {
        $resource_id = empty($params[0]) ? 'master' : $params[0];
        $resource_type = strtolower(substr($func, 3));
        if (empty(App::getInstance()->config[$resource_type][$resource_id])) {
            throw new NotFound(__CLASS__ . ": resource[{$resource_type}/{$resource_id}] not found.");
        }
        $config = App::getInstance()->config[$resource_type][$resource_id];
        $class = '\\SPF\\' . ucfirst($resource_type) . '\\' . $config['type'];
        return new $class($config);
    }
}
