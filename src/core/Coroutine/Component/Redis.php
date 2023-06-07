<?php

namespace SPF\Coroutine\Component;

use SPF\Coroutine\Redis as CoRedis;
use SPF;

class Redis extends Base
{
    protected $type = 'redis';

    /**
     * Redis constructor.
     * @param $config
     * @throws SPF\Exception\InvalidParam
     */
    public function __construct($config)
    {
        parent::__construct($config);
        SPF\App::getInstance()->beforeAction([$this, '_createObject'], SPF\App::coroModuleRedis);
        SPF\App::getInstance()->afterAction([$this, '_freeObject'], SPF\App::coroModuleRedis);
    }

    /**
     * @return bool|CoRedis
     */
    public function create()
    {
        $redis = new CoRedis($this->config);
        if ($redis->connect($this->config['host'], $this->config['port']) === false) {
            return false;
        }
        if (!empty($this->config['password'])) {
            $redis->auth($this->config['password']);
        }
        if (isset($this->config['database'])) {
            if (!$redis->select(intval($this->config['database']))) {
                return false;
            }
        }
        return $redis;
    }

    public function get($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }
        return $redis->get($key);
    }

    public function set($key, $value, $expire = null)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        if ($expire) {
            return $redis->setEx($key, $expire, $value);
        } else {
            return $redis->set($key, $value);
        }
    }

    /**
     * @param $key
     * @param $offset
     * @param $value
     * @return mixed
     */
    public function setBit($key, $offset, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }
        return $redis->setBit($key, $offset, $value);
    }

    /**
     * @param $key
     * @param $ttl
     * @param $value
     * @return mixed
     */
    public function setEx($key, $ttl, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->setEx($key, $ttl, $value);
    }

    /**
     * @param $key
     * @param $ttl
     * @param $value
     * @return mixed
     */
    public function psetEx($key, $ttl, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->psetEx($key, $ttl, $value);
    }

    /**
     * @param $key
     * @param $index
     * @param $value
     * @return mixed
     */
    public function lSet($key, $index, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lSet($key, $index, $value);
    }

    /**
     * @param $params
     * @return mixed
     */
    public function mGet($params)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->mGet($params);
    }

    /**
     * @param array $args
     * @return mixed
     */
    public function del(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->del(...$args);
    }

    /**
     * @param array $arg
     * @return mixed
     */
    public function hDel(...$arg)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hDel(...$arg);
    }

    /**
     * @param $key
     * @param $hashKey
     * @param $value
     * @return mixed
     */
    public function hSet($key, $hashKey, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hSet($key, $hashKey, $value);
    }

    /**
     * @param $key
     * @param $hashKeys
     * @return mixed
     */
    public function hMSet($key, $hashKeys)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hMSet($key, $hashKeys);
    }

    /**
     * @param $key
     * @param $hashKey
     * @param $value
     * @return mixed
     */
    public function hSetNx($key, $hashKey, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hSetNx($key, $hashKey, $value);
    }

    /**
     * @param array $args
     * @return mixed
     */
    public function delete(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->delete(...$args);
    }

    /**
     * @return mixed
     */
    public function mSet(array $array)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->mSet($array);
    }

    /**
     * @return mixed
     */
    public function mSetNx(array $array)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->mSetNx($array);
    }

    /**
     * @return mixed
     */
    public function getKeys($pattern)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->getKeys($pattern);
    }

    /**
     * @return mixed
     */
    public function keys($pattern)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->getKeys($pattern);
    }

    /**
     * @param $key [required]
     * @return mixed
     */
    public function exists($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->exists($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function type($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->type($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function strLen($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->strLen($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function lPop($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lPop($key);
    }

    /**
     * @return mixed
     */
    public function blPop($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->blPop($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function rPop($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->rPop($key);
    }

    /**
     * @return mixed
     */
    public function brPop(array $keys, $timeout)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->brPop($keys, $timeout);
    }

    /**
     * @return mixed
     */
    public function bRPopLPush($srcKey, $dstKey, $timeout)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->bRPopLPush($srcKey, $dstKey, $timeout);
    }

    /**
     * @return mixed
     */
    public function lSize($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lSize($key);
    }

    /**
     * @return mixed
     */
    public function lLen($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lLen($key);
    }

    /**
     * @return mixed
     */
    public function sSize($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sSize($key);
    }

    /**
     * @return mixed
     */
    public function scard($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sCard($key);
    }

    /**
     * @return mixed
     */
    public function sPop($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sPop($key);
    }

    /**
     * @param $key [required]
     * @return mixed
     */
    public function sMembers($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sMembers($key);
    }

    /**
     * @param $key
     * @return bool|mixed
     */
    public function sGetMembers($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sGetMembers($key);
    }

    /**
     * @param $key
     * @param int $count
     * @return bool|mixed
     */
    public function sRandMember($key, $count = 1)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sRandMember($key, $count);
    }

    /**
     * @return mixed
     */
    public function persist($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->persist($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function ttl($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->ttl($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function pttl($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->pttl($key);
    }

    /**
     * @return mixed
     */
    public function zCard($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zCard($key);
    }

    /**
     * @return mixed
     */
    public function zSize($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zSize($key);
    }

    /**
     * @return mixed
     */
    public function hLen($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hLen($key);
    }

    /**
     * @return mixed
     */
    public function hKeys($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hKeys($key);
    }

    /**
     * @return mixed
     */
    public function hVals($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hVals($key);
    }

    /**
     * @return mixed
     */
    public function hGetAll($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hGetAll($key);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function debug($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->debug($key);
    }

    /**
     * @return mixed
     */
    public function restore($key, $ttl, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->restore($key, $ttl, $value);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function dump($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->dump($key);
    }

    /**
     * @return mixed
     */
    public function renameKey($srcKey, $dstKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->renameKey($srcKey, $dstKey);
    }

    /**
     * @return mixed
     */
    public function rename($srcKey, $dstKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->rename($srcKey, $dstKey);
    }

    /**
     * @return mixed
     */
    public function renameNx($srcKey, $dstKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->renameNx($srcKey, $dstKey);
    }

    /**
     * @return mixed
     */
    public function rpoplpush($srcKey, $dstKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->rpoplpush($srcKey, $dstKey);
    }

    /**
     * @return mixed
     */
    public function randomKey()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->randomKey();
    }

    /**
     * @return mixed
     */
    public function ping()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->ping();
    }

    /**
     * @return mixed
     */
    public function auth($password)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->auth($password);
    }

    /**
     * @return mixed
     */
    public function unwatch()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->unwatch();
    }

    /**
     * @return mixed
     */
    public function watch($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->watch($key);
    }

    /**
     * @return mixed
     */
    public function save()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->save();
    }

    /**
     * @return mixed
     */
    public function bgSave()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->bgSave();
    }

    /**
     * @return mixed
     */
    public function lastSave()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lastSave();
    }

    /**
     * @return mixed
     */
    public function flushDB()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->flushDB();
    }

    /**
     * @return mixed
     */
    public function flushAll()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->flushAll();
    }

    /**
     * @return mixed
     */
    public function dbSize()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->dbSize();
    }

    /**
     * @return mixed
     */
    public function bgrewriteaof()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->bgrewriteaof();
    }

    /**
     * @return mixed
     */
    public function time()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->time();
    }

    /**
     * @return mixed
     */
    public function role()
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->role();
    }

    /**
     * @return mixed
     */
    public function setRange($key, $offset, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->setRange($key, $offset, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function setNx($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->setNx($key, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function getSet($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->getSet($key, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function append($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->append($key, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function lPushx($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lPushx($key, $value);
    }

    /**
     * @return mixed
     */
    public function lPush(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->lPush(...$args);
    }

    /**
     * @param array $args
     * @return mixed
     */
    public function rPush(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->rPush(...$args);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function rPushx($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->rPushx($key, $value);
    }

    /**
     * @param $key [required]
     * @param $value [required]
     * @return mixed
     */
    public function sContains($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sContains($key, $value);
    }

    /**
     * @param $key [required]
     * @param $value [required]
     * @return mixed
     */
    public function sismember($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sismember($key, $value);
    }

    /**
     * @param $key [required]
     * @param $value [required]
     * @return mixed
     */
    public function zScore($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zScore($key, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function zRank($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRank($key, $value);
    }

    /**
     * @param $key[required]
     * @param $value[required]
     * @return mixed
     */
    public function zRevRank($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRevRank($key, $value);
    }

    /**
     * @param $key
     * @param $hashKey
     * @return bool|mixed
     */
    public function hGet($key, $hashKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hGet($key, $hashKey);
    }

    /**
     * @return mixed
     */
    public function hMGet($key, $hashKeys)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hMGet($key, $hashKeys);
    }

    /**
     * @return mixed
     */
    public function hExists($key, $hashKey)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hExists($key, $hashKey);
    }

    /**
     * @return mixed
     */
    public function publish($channel, $message)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->publish($channel, $message);
    }

    /**
     * @param $key
     * @param $value
     * @param $member
     * @return bool|mixed
     */
    public function zIncrBy($key, $value, $member)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zIncrBy($key, $value, $member);
    }

    /**
     * @param array ...$args
     * @return bool|mixed
     */
    public function zAdd(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zAdd(...$args);
    }

    /**
     * @return mixed
     */
    public function zDeleteRangeByScore($key, $start, $end)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zDeleteRangeByScore($key, $start, $end);
    }

    /**
     * @return mixed
     */
    public function zRemRangeByScore($key, $start, $end)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRemRangeByScore($key, $start, $end);
    }

    /**
     * @return mixed
     */
    public function zCount()
    {
    }

    /**
     * @return mixed
     */
    public function zRange($key, $start, $end, $withscores = null)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRange($key, $start, $end, $withscores);
    }

    /**
     * @return mixed
     */
    public function zRevRange($key, $start, $end, $withscore = null)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRevRange($key, $start, $end, $withscore);
    }

    /**
     * @return mixed
     */
    public function zRangeByScore()
    {
    }

    /**
     * @return mixed
     */
    public function zRevRangeByScore()
    {
    }

    /**
     * @return mixed
     */
    public function zRangeByLex()
    {
    }

    /**
     * @return mixed
     */
    public function zRevRangeByLex()
    {
    }

    /**
     * @return mixed
     */
    public function zInter()
    {
    }

    /**
     * @return mixed
     */
    public function zinterstore()
    {
    }

    /**
     * @return mixed
     */
    public function zUnion()
    {
    }

    /**
     * @return mixed
     */
    public function zunionstore()
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function incrBy($key, $integer)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->incrBy($key, $integer);
    }

    /**
     * @return mixed
     */
    public function hIncrBy($key, $hashKey, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->hIncrBy($key, $hashKey, $value);
    }

    /**
     * @param $key [required]
     * @return mixed
     */
    public function incr($key)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->incr($key);
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function decrBy($key, $integer)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->decrBy($key, $integer);
    }

    /**
     * @param $key[required]
     * @return mixed
     */
    public function decr($key)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function getBit($key, $integer)
    {
    }

    /**
     * @return mixed
     */
    public function lInsert()
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function lGet($key, $integer)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function lIndex($key, $integer)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function setTimeout($key, $integer)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function expire($key, $integer)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->expire($key, $integer);
    }

    /**
     * @return mixed
     */
    public function pexpire()
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function expireAt($key, $integer)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function pexpireAt($key, $integer)
    {
    }

    /**
     * @param $key[required]
     * @param $integer[required]
     * @return mixed
     */
    public function move($key, $integer)
    {
    }

    /**
     * @return mixed
     */
    public function select()
    {
    }

    /**
     * @return mixed
     */
    public function getRange()
    {
    }

    /**
     * @return mixed
     */
    public function listTrim()
    {
    }

    /**
     * @return mixed
     */
    public function ltrim()
    {
    }

    /**
     * @return mixed
     */
    public function lGetRange()
    {
    }

    /**
     * @return mixed
     */
    public function lRange()
    {
    }

    /**
     * @return mixed
     */
    public function lRem()
    {
    }

    /**
     * @return mixed
     */
    public function lRemove()
    {
    }

    /**
     * @return mixed
     */
    public function zDeleteRangeByRank()
    {
    }

    /**
     * @return mixed
     */
    public function zRemRangeByRank()
    {
    }

    /**
     * @param $key[required]
     * @param $float_number[required]
     * @return mixed
     */
    public function incrByFloat($key, $float_number)
    {
    }

    /**
     * @return mixed
     */
    public function hIncrByFloat()
    {
    }

    /**
     * @return mixed
     */
    public function bitCount()
    {
    }

    /**
     * @return mixed
     */
    public function bitOp()
    {
    }

    /**
     * @return mixed
     */
    public function sAdd($key, $value)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sAdd($key, $value);
    }

    /**
     * @return mixed
     */
    public function sMove()
    {
    }

    /**
     * @return mixed
     */
    public function sDiff()
    {
    }

    /**
     * @return mixed
     */
    public function sDiffStore()
    {
    }

    /**
     * @return mixed
     */
    public function sUnion()
    {
    }

    /**
     * @return mixed
     */
    public function sUnionStore()
    {
    }

    /**
     * @return mixed
     */
    public function sInter()
    {
    }

    /**
     * @return mixed
     */
    public function sInterStore()
    {
    }

    /**
     * @return mixed
     */
    public function sRemove($key, $member)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sRemove($key, $member);
    }

    /**
     * @return mixed
     */
    public function srem($key, $member)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->sRemove($key, $member);
    }

    /**
     * @return mixed
     */
    public function zDelete(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zDelete(...$args);
    }

    /**
     * @return mixed
     */
    public function zRemove(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRemove(...$args);
    }

    /**
     * @return mixed
     */
    public function zRem(...$args)
    {
        /**
         * @var $redis CoRedis
         */
        $redis = $this->_getObject();
        if (!$redis) {
            return false;
        }

        return $redis->zRem(...$args);
    }

    /**
     * @return mixed
     */
    public function pSubscribe()
    {
    }

    /**
     * @return mixed
     */
    public function subscribe()
    {
    }

    /**
     * @return mixed
     */
    public function multi()
    {
    }

    /**
     * @return mixed
     */
    public function exec()
    {
    }

    /**
     * @return mixed
     */
    public function evaluate()
    {
    }

    /**
     * @return mixed
     */
    public function evalSha()
    {
    }

    /**
     * @return mixed
     */
    public function script()
    {
    }
}
