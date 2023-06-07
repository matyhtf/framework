<?php
namespace SPF\Model;

/**
 * 字典操作模型
 * @author Administrator
 *
 */
class Dict extends \SPF\Model
{
    public $table = 'swoole_dict';
    public $cache;
    public $_data;
    public $if_cache = false;
    public $cache_prefix = '';
    public $expire = 0;

    /**
     * 设置缓存前缀
     * @param $prefix
     * @return unknown_type
     */
    public function setCachePrefix($prefix='swoole_dict_')
    {
        $this->cache_prefix = $prefix;
    }
    /**
     * 设置缓存
     * @param $cache
     * @param $expire
     * @return unknown_type
     */
    public function setCache($cache, $expire=3600)
    {
        $this->cache = $cache;
        $this->if_cache = true;
        $this->expire = $expire;
    }
    /**
     * 按ID查询
     * @param $id
     * @return $value
     */
    public function iget($id)
    {
        return $this->get($id)->get();
    }
    /**
     * 按父ID查询得出列表
     * @param $fid
     * @param $order
     * @return unknown_type
     */
    public function igets($fid=0, $order = 'id')
    {
        $gets['fid'] = $fid;
        $gets['order'] = $order;
        return $this->gets($gets);
    }
    /**
     * 按路径查询，得到列表
     * @param $kpath
     * @param $order
     * @return $list
     */
    public function pgets($kpath, $order='')
    {
        $gets['kpath'] = $kpath;
        $gets['order'] = $order;
        return $this->gets($gets);
    }
    /**
     * 按路径查询，得到一个值
     * @param $kpath
     * @param $order
     * @return $list
     */
    public function pget($kpath, $kname)
    {
        $path = "$kpath/$kname";
        if ($this->if_cache) {
            $cache_data = $this->cache->get($this->cache_prefix.$path);
        } else {
            $cache_data = false;
        }
        if ($cache_data) {
            return $cache_data;
        } else {
            $get['kpath'] = $kpath;
            $get['limit'] = 1;
            $get['ckname'] = $kname;
            $res = 	$this->gets($get);
            if (empty($res)) {
                Error::pecho("Not found $kpath/$kname");
                $de = debug_backtrace();
                foreach ($de as $d) {
                    echo $d['file'],':',$d['line'],"\n<br />";
                }
                return false;
            }
            if ($this->if_cache) {
                $this->cache->set($this->cache_prefix.$path, $res[0], $this->expire);
            }
            return $res[0];
        }
    }

    /**
     * KEY查询方式，找出一个项
     * @param $keyid
     * @return unknown_type
     */
    public function kget($keyid)
    {
        if ($this->if_cache) {
            $cache_data = $this->cache->get($this->cache_prefix.$keyid);
        } else {
            $cache_data = false;
        }
        if ($cache_data) {
            return $cache_data;
        } else {
            $get['keyid'] = $keyid;
            $get['limit'] = 1;
            $data = $this->gets($get);
            if ($this->if_cache) {
                $this->cache->set($this->cache_prefix.$keyid, $data[0], $this->expire);
            }
            return $data[0];
        }
    }
    /**
     * KEY查询方式，找出多个子项
     * @param $keyid
     * @return unknown_type
     */
    public function kgets($fkey)
    {
        $get['fkey'] = $fkey;
        $data = $this->gets($get);
        return $data;
    }
}
