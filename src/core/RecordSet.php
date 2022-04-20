<?php
namespace SPF;

/**
 * 数据结果集，由Record组成
 * 通过foreach遍历，可以产生单条的Record对象，对每条数据进行操作
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage Model
 */
class RecordSet implements \Iterator
{
    protected $_list = array();
    protected $table = '';
    protected $db;
    /**
     * @var SelectDb
     */
    protected $db_select;

    public $primary = "";

    public $_current_id = 0;

    function __construct($db, $table, $primary, $select)
    {
        $this->table = $table;
        $this->primary = $primary;
        $this->db = $db;
        $this->db_select = new SelectDB($db);
        $this->db_select->from($table);
        $this->db_select->primary = $primary;
        $this->db_select->select($select);
        $this->db_select->order($this->primary . " desc");
    }
    /**
     * 获取得到的数据
     * @return array
     */
    function get()
    {
        return $this->_list;
    }
    /**
     * 制定查询的参数，再调用数据之前进行
     * 参数为SQL SelectDB的put语句
     * @param array $params
     * @return bool
     */
    function params($params)
    {
        return $this->db_select->put($params);
    }
    /**
     * 过滤器语法，参数为SQL SelectDB的where语句
     * @param array $params
     * @return null
     */
    function filter($where)
    {
        $this->db_select->where($where);
    }
    /**
     * 增加过滤条件，$field = $value
     * @return unknown_type
     */
    function eq($field, $value)
    {
        $this->db_select->equal($field,$value);
    }
    /**
     * 过滤器语法，参数为SQL SelectDB的orwhere语句
     * @param $params
     */
    function orfilter($where)
    {
        $this->db_select->orwhere($where);
    }
    /**
     * 获取一条数据
     * 参数可以制定返回的字段
     * @param $field
     */
    function fetch($field='')
    {
        return $this->db_select->getone($field);
    }

    /**
     * 获取全部数据
     */
    function fetchall()
    {
        return $this->db_select->getall();
    }

    function __set($key, $v)
    {
        $this->db_select->$key = $v;
    }

    function __call($method, $argv)
    {
        return call_user_func_array(array($this->db_select, $method), $argv);
    }

    public function rewind()
    {
        if (empty($this->_list))
        {
            $this->_list = $this->db_select->getall();
        }
        $this->_current_id = 0;
    }

    public function key()
    {
        return $this->_current_id;
    }

    public function current()
    {
        $record = new Record(0, $this->db, $this->table, $this->primary);
        $record->put($this->_list[$this->_current_id]);
        $record->_current_id = $this->_list[$this->_current_id][$this->primary];
        return $record;
    }

    public function next()
    {
        $this->_current_id++;
    }

    public function valid()
    {
        if (isset($this->_list[$this->_current_id]))
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}
