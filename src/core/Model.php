<?php
namespace SPF;

use SPF\Component\QueryBuilder;

/**
 * Model类，ORM基础类，提供对某个数据库表的接口
 * @author Tianfeng Han
 */
class Model
{
    public $_data = array(); //数据库字段的具体值
    /**
     * @var IDatabase
     */
    public $db;
    public $app;

    public $primary = "id";
    public $foreignkey = 'catid';

    public $_struct;
    public $_form;
    public $_form_secret = true;

    public $table = "";
    protected $_table_before_shard;

    /**
     * 表切片参数
     *
     * @var int
     */
    public $tablesize = 1000000;
    public $fields;
    public $select = '*';

    public $create_sql='';

    public $if_cache = false;
    protected $dbs;

    /**
     * 构造函数
     * @param App $app
     * @param string $db_key 选择哪个数据库
     */
    public function __construct(App $app, $db_key = 'master')
    {
        $this->db = $app->db($db_key);
        $this->dbs = new SelectDB($this->db);
        $this->app = $app;
    }

    /**
     * 按ID切分表
     *
     * @param $id
     * @return null
     */
    public function shard_table($id)
    {
        if (empty($this->_table_before_shard)) {
            $this->_table_before_shard = $this->table;
        }
        $table_id = intval($id / $this->tablesize);
        $this->table = $this->_table_before_shard . '_' . $table_id;
    }

    /**
     * 获取主键$primary_key为$object_id的一条记录对象(Record Object)
     * 如果参数为空的话，则返回一条空白的Record，可以赋值，产生一条新的记录
     * @param $object_id
     * @param $where
     * @return Record Object
     */
    public function get($object_id = 0, $where = '')
    {
        return new Record($object_id, $this->db, $this->table, $this->primary, $where, $this->select);
    }

    /**
     * 获取表的一段数据，查询的参数由$params指定
     * @param $params
     * @param $pager Pager
     * @throws \Exception
     * @return array
     */
    public function gets($params, &$pager=null)
    {
        if (empty($params)) {
            throw new \Exception("no params.");
        }

        $selectdb = new SelectDB($this->db);
        $selectdb->from($this->table);
        $selectdb->primary = $this->primary;
        $selectdb->select($this->select);

        if (!isset($params['order'])) {
            $params['order'] = "`{$this->table}`.{$this->primary} desc";
        }
        $selectdb->put($params);

        if (isset($params['page'])) {
            $selectdb->paging();
            $pager = $selectdb->pager;
        }

        return $selectdb->getall();
    }

    /**
     * 插入一条新的记录到表
     * @param $data array 必须是键值（表的字段对应值）对应
     * @return int
     */
    public function put($data)
    {
        if (empty($data) or !is_array($data)) {
            return false;
        }
        if ($this->db->insert($data, $this->table)) {
            $lastInsertId = $this->db->lastInsertId();
            if ($lastInsertId == 0) {
                return true;
            } else {
                return $lastInsertId;
            }
        } else {
            return false;
        }
    }

    /**
     * 批量插入数据
     * @param array $fields
     * @param array $data
     * @return bool
     */
    public function puts(array $fields, array $data)
    {
        $c = count($data);
        if ($c <= 0 or count($fields) <= 0) {
            return false;
        }

        return $this->db->insertBatch($fields, $data, $this->table);
    }

    /**
     * 更新ID为$id的记录,值为$data关联数组
     * @param $id
     * @param $data
     * @param $where string 指定匹配字段，默认为主键
     * @return bool
     */
    public function set($id, $data, $where = '')
    {
        if (empty($where)) {
            $where = $this->primary;
        }
        return $this->db->update($id, $data, $this->table, $where);
    }

    /**
     * 更新一组数据
     * @param array $data 更新的数据
     * @param array $params update的参数列表
     * @return bool
     * @throws \Exception
     */
    public function sets($data, $params)
    {
        if (empty($params)) {
            throw new \Exception("Model sets params is empty!");
        }
        $selectdb = new SelectDB($this->db);
        $selectdb->from($this->table);
        $selectdb->put($params);
        return $selectdb->update($data);
    }

    /**
     * 删除一条数据主键为$id的记录，
     * @param $id
     * @param $where string 指定匹配字段，默认为主键
     * @return true/false
     */
    public function del($id, $where=null)
    {
        if ($where == null) {
            $where = $this->primary;
        }
        return $this->db->delete($id, $this->table, $where);
    }

    /**
     * 删除一条数据包含多个参数
     * @param $params
     * @return bool
     * @throws \Exception
     */
    public function dels($params)
    {
        if (empty($params)) {
            throw new \Exception("Model dels params is empty!");
        }
        $selectdb = new SelectDB($this->db);
        $selectdb->from($this->table);
        $selectdb->put($params);
        $selectdb->delete();
        return true;
    }

    /**
     * 返回符合条件的记录数
     * @param array $params
     * @return true/false
     */
    public function count($params)
    {
        $selectdb = new SelectDB($this->db);
        $selectdb->from($this->table);
        $selectdb->put($params);
        return $selectdb->count();
    }

    /**
     * 获取到所有表记录的接口，通过这个接口可以访问到数据库的记录
     * @return RecordSet Object (这是一个接口，不包含实际的数据)
     */
    public function all()
    {
        return new RecordSet($this->db, $this->table, $this->primary, $this->select);
    }

    /**
     * 建立表，必须在Model类中，指定create_sql
     * @return bool
     */
    public function createTable()
    {
        if ($this->create_sql) {
            return $this->db->query($this->create_sql);
        } else {
            return false;
        }
    }

    /**
     * 获取表状态
     * @return array 表的status，包含了自增ID，计数器等状态数据
     */
    final public function getStatus()
    {
        return $this->db->query("show table status from ".DBNAME." where name='{$this->table}'")->fetch();
    }
    /**
     * 获取一个数据列表，功能类似于gets，此方法仅用于SiaoCMS，不作为同样类库的方法
     * @param $params
     * @param $get
     * @return array
     */
    public function getList(&$params, $get='data')
    {
        $selectdb = new SelectDB($this->db);
        $selectdb->from($this->table);
        $selectdb->select($this->select);
        $selectdb->limit(isset($params['row']) ? $params['row'] : 10);
        unset($params['row']);
        $selectdb->order(isset($params['order']) ? $params['order'] : $this->primary . ' desc');
        unset($params['order']);

        if (isset($params['typeid'])) {
            $selectdb->where($this->foreignkey . '=' . $params['typeid']);
            unset($params['typeid']);
        }
        $selectdb->put($params);
        if (array_key_exists('page', $params)) {
            $selectdb->paging();
            $php = SPF\App::getInstance();
            ;
            $php->env['page'] = $params['page'];
            $php->env['start'] = 10 * intval($params['page'] / 10);
            if ($selectdb->pages > 10 and $params['page'] < $php->env['start']) {
                $php->env['more'] = 1;
            }
            $php->env['end'] = $selectdb->pages - $php->env['start'];
            $php->env['pages'] = $selectdb->pages;
            $php->env['pagesize'] = $selectdb->page_size;
            $php->env['num'] = $selectdb->num;
        }
        if ($get === 'data') {
            return $selectdb->getall();
        } elseif ($get === 'sql') {
            return $selectdb->getsql();
        }
    }

    /**
     * 获取一个键值对应的结构，键为表记录主键的值，值为记录数据或者其中一个字段的值
     * @param $gets
     * @param $field
     * @return array
     * @throws \Exception
     */
    public function getMap($gets, $field = null)
    {
        $list = $this->gets($gets);
        $new = array();
        foreach ($list as $li) {
            if (empty($field)) {
                $new[$li[$this->primary]] = $li;
            } else {
                $new[$li[$this->primary]] = $li[$field];
            }
        }
        return $new;
    }

    /**
     * 获取一个2层的树状结构
     * @param $gets
     * @param $category
     * @param $order
     * @return unknown_type
     */
    public function getTree($gets, $category='fid', $order='id desc')
    {
        $gets['order'] = $category . ',' . $order;
        $list = $this->gets($gets);
        foreach ($list as $li) {
            if ($li[$category] == 0) {
                $new[$li[$this->primary]] = $li;
            } else {
                $new[$li[$category]]['child'][$li[$this->primary]] = $li;
            }
        }
        return $new;
    }

    /**
     * 检测是否存在数据，实际可以用count代替，0为false，>0为true
     * @param $gets
     * @return bool
     */
    public function exists($gets)
    {
        $c = $this->count($gets);
        if ($c > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 检测表是否存在
     */
    public function existsTable()
    {
        $data = $this->db->query("show tables like '{$this->table}'")->fetch();
        if (empty($data)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * 获取表的字段描述
     * @return array
     */
    public function desc()
    {
        return $this->db->query('describe '.$this->table)->fetchall();
    }
    /**
     * 自动生成表单
     *
     * @param $set_id
     *
     * @return unknown_type
     */
    public function getForm($set_id = 0)
    {
        $this->_form_();
        //传入ID，修改表单
        if ($set_id) {
            $data = $this->get((int)$set_id)->get();
            foreach ($this->_form as $k => &$f) {
                $f['value'] = $data[$k];
            }
            if (method_exists($this, "_set_")) {
                $this->_set_();
            }

            if ($this->_form_secret) {
                Form::secret(get_class($this) . '_set');
            }
        }
        //增加表单
        elseif (method_exists($this, "_add_")) {
            $this->_add_();
            if ($this->_form_secret) {
                Form::secret(get_class($this) . '_add');
            }
        }
        return Form::autoform($this->_form);
    }

    /**
     *
     * @param  string $error 出错时设置
     *
     * @return true or false
     */
    public function checkForm($input, $method, &$error)
    {
        if ($this->_form_secret) {
            $k = 'form_'.get_class($this).'_'.$method;
            if (!isset($_SESSION)) {
                session();
            }
            if ($_COOKIE[$k]!=$_SESSION[$k]) {
                $error = '错误的请求';
                return false;
            }
        }
        $this->_form_();
        return Form::checkInput($input, $this->_form, $error);
    }

    /**
     * @param $fields
     * @return QueryBuilder
     */
    public function select($fields = '*')
    {
        return new QueryBuilder($this->db, $this->table, $fields);
    }

    public static function create(string $db_key = 'master')
    {
        return new static(App::getInstance(), $db_key);
    }
}
