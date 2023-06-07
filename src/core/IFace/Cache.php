<?php
namespace SPF\IFace;

interface Cache
{
    /**
     * 设置缓存
     * @param $key
     * @param $value
     * @param $expire
     * @return bool
     */
    public function set($key, $value, $expire=0);
    /**
     * 获取缓存值
     * @param $key
     * @return mixed
     */
    public function get($key);
    /**
     * 删除缓存值
     * @param $key
     * @return bool
     */
    public function delete($key);
}
