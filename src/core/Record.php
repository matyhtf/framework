<?php
namespace SPF;

use SPF\Component\Observer;

/**
 * Record类，表中的一条记录，通过对象的操作，映射到数据库表
 * 可以使用属性访问，也可以通过关联数组方式访问
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage Model
 */
class Record extends Observer implements \ArrayAccess
{
    protected $_data = array();
    protected $_original_data = null;
    protected $_update = array();
    protected $_change = 0;
    protected $_save = false;

    /**
     * @var \SPF\Database
     */
    public $db;

    public $primary = "id";
    public $table = "";

    public $_current_id = 0;
    public $_currend_key;
    public $_delete = false;

    const STATE_EMPTY  = 0;
    const STATE_INSERT = 1;
    const STATE_UPDATE = 2;

    const CACHE_KEY_PREFIX = 'swoole_record_';

    /**
     * @param        $id
     * @param        $db \SPF\Database
     * @param        $table
     * @param        $primary
     * @param string $where
     * @param string $select
     */
    public function __construct($id, $db, $table, $primary, $where = '', $select = '*')
    {
        $this->db = $db;
        $this->_current_id = $id;
        $this->table = $table;
        $this->primary = $primary;

        if (empty($where)) {
            $where = $primary;
        }

        if (!empty($this->_current_id)) {
            $obj = $this->db->query("select {$select} from {$this->table} where {$where} ='{$id}' limit 1");
            if (!is_bool($obj)) {
                $res = $obj->fetch();
                if (!empty($res)) {
                    $this->_original_data = $this->_data = $res;
                    if (isset($this->_data[$this->primary])) {
                        $this->_current_id = $this->_data[$this->primary];
                    } else {
                        $this->_current_id = $id;
                    }
                    $this->_change = self::STATE_INSERT;
                }
            }
        }
    }


    /**
     * 是否存在
     * @return bool
     */
    public function exist()
    {
        return !empty($this->_data);
    }

    /**
     * 将关联数组压入object中，赋值给各个字段
     * @param $data
     * @return void
     */
    public function put($data)
    {
        if ($this->_change == self::STATE_INSERT) {
            $this->_change = self::STATE_UPDATE;
            $this->_update = $data;
        } elseif ($this->_change == self::STATE_EMPTY) {
            $this->_change = self::STATE_INSERT;
            $this->_data = $data;
        }
    }

    /**
     * 获取数据数组
     * @return array
     */
    public function get()
    {
        return $this->_data;
    }

    /**
     * 获取原始数据
     * @return null | array
     */
    public function getOriginalData()
    {
        return $this->_original_data;
    }

    /**
     * 获取属性
     * @param $property
     *
     * @return null
     */
    public function __get($property)
    {
        if (isset($this->_data[$property])) {
            return $this->_data[$property];
        } else {
            Error::pecho("Record object no property: $property");
            return null;
        }
    }

    public function __set($property, $value)
    {
        if ($this->_change == self::STATE_INSERT or $this->_change == self::STATE_UPDATE) {
            $this->_change = self::STATE_UPDATE;
            $this->_update[$property] = $value;
            $this->_data[$property] = $value;
        } else {
            $this->_data[$property] = $value;
        }
        $this->_save = true;
    }

    /**
     * 保存对象数据到数据库
     * 如果是空白的记录，保存则会Insert到数据库
     * 如果是已存在的记录，保持则会update，修改过的值，如果没有任何值被修改，则不执行SQL
     * @return bool
     */
    public function save()
    {
        $this->_save = false;
        if ($this->_change == 0 or $this->_change == 1) {
            if ($this->db->insert($this->_data, $this->table) === false) {
                return false;
            }
            //改变状态
            $this->_change = 1;
            $this->_current_id = $this->db->lastInsertId();
        } elseif ($this->_change == 2) {
            $update = $this->_update;
            unset($update[$this->primary]);
            if ($this->db->update($this->_current_id, $update, $this->table, $this->primary) === false) {
                return false;
            }
        }
        $this->notify();
        return true;
    }

    public function update()
    {
        $update = $this->_data;
        unset($update[$this->primary]);
        if ($this->db->update($this->_current_id, $this->_update, $this->table, $this->primary) === false) {
            return false;
        }
        $this->notify();
        return true;
    }

    public function __destruct()
    {
        if ($this->_save) {
            $this->save();
        }
    }

    /**
     * 删除数据库中的此条记录
     * @return bool
     */
    public function delete()
    {
        if ($this->db->delete($this->_current_id, $this->table, $this->primary) === false) {
            return false;
        }
        $this->_delete = true;
        $this->notify();
        return true;
    }

    public function offsetExists($key)
    {
        return isset($this->_data[$key]);
    }

    public function offsetGet($key)
    {
        return $this->_data[$key];
    }

    public function offsetSet($key, $value)
    {
        $this->_data[$key] = $value;
    }

    public function offsetUnset($key)
    {
        unset($this->_data[$key]);
    }
}
