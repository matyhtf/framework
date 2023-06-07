<?php
namespace SPF;

/**
 * 查询数据库的封装类，基于底层数据库封装类，实现SQL生成器
 * 注：仅支持MySQL，不兼容其他数据库的SQL语法
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage Database
 */
class SelectDB
{
    public static $error_call = '';
    public static $allow_regx = '#^([a-z0-9\(\)\._=\-\+\*\`\s\'\",]+)$#i';

    public $table = '';
    public $primary = 'id';
    public $select = '*';
    public $sql = '';
    public $limit = '';
    public $where = '';
    public $order = '';
    public $group = '';
    public $use_index = '';
    public $having = '';
    public $join = '';
    public $union = '';
    public $for_update = '';

    /**
     * @var RecordSet
     */
    protected $result;

    //Union联合查询
    private $if_union = false;
    private $union_select = '';

    //Join连接表
    private $if_join = false;
    private $if_add_tablename = false;

    protected $extraParmas = array();

    /**
     * 缓存选项
     * @var array
     */
    protected $cacheOptions = array();

    //Count计算
    private $count_fields = '*';

    public $page_size = 10;
    public $num = 0;
    public $pages = 0;
    public $page = 0;

    /**
     * @var Pager
     */
    public $pager = null;

    public $auto_cache = false;
    protected $enableCache;

    const CACHE_PREFIX = 'swoole_selectdb_';
    const CACHE_LIFETIME = 300;

    public $RecordSet;

    public $is_execute = 0;

    public $result_filter = array();

    public $call_by = 'func';

    /**
     * @var \SPF\Database
     */
    public $db;

    /**
     * @param $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * 初始化，select的值，参数$where可以指定初始化哪一项
     * @param $what
     */
    public function init($what='')
    {
        if ($what=='') {
            $this->table='';
            $this->primary='id';
            $this->select='*';
            $this->sql='';
            $this->limit='';
            $this->where='';
            $this->order='';
            $this->group='';
            $this->use_index='';
            $this->join='';
            $this->union='';
        } else {
            $this->$what = '';
        }
    }

    /**
     * 字段等于某个值，支持子查询，$where可以是对象
     * @param $field
     * @param $_where
     */
    public function equal($field, $_where)
    {
        if ($_where instanceof SelectDB) {
            $where = $field.'=('.$_where->getsql().')';
        } else {
            $where = "`$field`='$_where'";
        }
        $this->where($where);
    }

    /**
     * 指定表名，可以使用table1,table2
     * @param $table
     */
    public function from($table)
    {
        if (strpos($table, "`") === false) {
            $this->table= "`".$table."`";
        } else {
            $this->table=$table;
        }
    }

    /**
     * 指定查询的字段，select * from table
     * 可多次使用，连接多个字段
     * @param $select
     * @param $force
     * @return null
     */
    public function select($select, $force = false)
    {
        if (is_array($select)) {
            $select = implode(',', $select);
        }
        if ($this->select == "*" or $force) {
            $this->select = $select;
        } else {
            $this->select = $this->select . ',' . $select;
        }
    }

    /**
     * where参数，查询的条件
     * @return null
     */
    public function where()
    {
        $args = func_get_args();
        if (count($args) == 1) {
            $where = $args[0];
        } else {
            list($field, $expr, $value) = $args;
            $where = '`'.str_replace('`', '', $field).'`' . $this->db->quote($expr) . " '".$this->db->quote($value)."'";
        }

        if ($this->where == "") {
            $this->where = "where " . $where;
        } else {
            $this->where = $this->where . " and " . $where;
        }
    }

    /**
     * 指定查询所使用的索引字段
     * @param $field
     * @return null
     */
    public function useIndex($field)
    {
        self::sql_safe($field);
        $this->use_index = "use index($field)";
    }

    /**
     * 相似查询like
     * @param $field
     * @param $like
     * @return null
     */
    public function like($field, $like)
    {
        self::sql_safe($field);
        $this->where($field, 'like', $like);
    }

    /**
     * 使用or连接的条件
     * @param $where
     * @return null
     */
    public function orwhere($where)
    {
        $args = func_get_args();
        if (count($args) == 1) {
            $where = $args[0];
        } else {
            list($field, $expr, $value) = $args;
            $where = '`'.str_replace('`', '', $field).'`' . $this->db->quote($expr) . " '".$this->db->quote($value)."'";
        }

        if ($this->where == "") {
            $this->where = "where " . $where;
        } else {
            $this->where = $this->where . " or " . $where;
        }
    }

    /**
     * 查询的条数
     * @param $limit
     * @return null
     */
    public function limit($limit)
    {
        if (!empty($limit)) {
            $_limit = explode(',', $limit, 2);
            if (count($_limit) == 2) {
                $this->limit = 'limit ' . (int)$_limit[0] . ',' . (int)$_limit[1];
            } else {
                $this->limit = "limit " . (int)$limit;
            }
        } else {
            $this->limit = '';
        }
    }

    /**
     * 指定排序方式
     * @param $order
     * @return null
     */
    public function order($order)
    {
        if (!empty($order)) {
            self::sql_safe($order);
            $this->order = "order by $order";
        } else {
            $this->order = '';
        }
    }

    /**
     * 组合方式
     * @param $group
     * @return null
     */
    public function group($group)
    {
        if (!empty($group)) {
            self::sql_safe($group);
            $this->group = "group by $group";
        } else {
            $this->group = '';
        }
    }

    /**
     * group后条件
     * @param $having
     * @return null
     */
    public function having($having)
    {
        if (!empty($having)) {
            $this->having = "HAVING $having";
        } else {
            $this->having = '';
        }
    }

    /**
     * find_in_set语法
     * @param $field
     * @param $find
     */
    public function find($field, $find)
    {
        $this->where("FIND_IN_SET('" . $this->db->quote($find) . "', `{$field}`)");
    }

    /**
     * IN条件
     * @param $field
     * @param $ins
     * @return null
     */
    public function in($field, $ins)
    {
        if (is_array($ins)) {
            $ins = implode(',', $ins);
        } else {
            //去掉两边的分号
            $ins = trim($ins, ',');
        }
        $this->where("`$field` IN ({$ins})");
    }

    /**
     * NOT IN条件
     * @param $field
     * @param $ins
     * @return null
     */
    public function notin($field, $ins)
    {
        if (is_array($ins)) {
            $ins = implode(',', $ins);
        } else {
            //去掉两边的分号
            $ins = trim($ins, ',');
        }
        $this->where("`$field` NOT IN ({$ins})");
    }

    /**
     * INNER连接
     * @param $table_name
     * @param $on
     * @return null
     */
    public function join($table_name, $on)
    {
        $this->join .= "INNER JOIN `{$table_name}` ON ({$on})";
    }

    /**
     * 左连接
     * @param $table_name
     * @param $on
     * @return null
     */
    public function leftjoin($table_name, $on)
    {
        $this->join .= "LEFT JOIN `{$table_name}` ON ({$on})";
    }

    /**
     * 右连接
     * @param $table_name
     * @param $on
     * @return null
     */
    public function rightjoin($table_name, $on)
    {
        $this->join .= "RIGHT JOIN `{$table_name}` ON ({$on})";
    }

    /**
     * 分页参数,指定每页数量
     * @param $pagesize
     * @return null
     */
    public function pagesize($pagesize)
    {
        $this->page_size = (int)$pagesize;
    }

    /**
     * 分页参数,指定当前页数
     * @param $page
     * @return null
     */
    public function page($page)
    {
        $this->page = (int)$page;
    }

    /**
     * 主键查询条件
     * @param $id
     * @return null
     */
    public function id($id)
    {
        $this->where("`{$this->primary}` = '$id'");
    }

    /**
     * 启用缓存
     * @param $params
     */
    public function cache($params = true)
    {
        if ($params === false) {
            $this->enableCache = false;
        } else {
            $this->cacheOptions = $params;
            $this->enableCache = true;
        }
    }

    /**
     * 产生分页
     * @return null
     */
    public function paging()
    {
        $this->num = $this->count();
        $offset = ($this->page - 1) * $this->page_size;
        if ($offset < 0) {
            $offset = 0;
        }
        if ($this->num % $this->page_size > 0) {
            $this->pages = intval($this->num / $this->page_size) + 1;
        } else {
            $this->pages = $this->num / $this->page_size;
        }
        $this->limit($offset . ',' . $this->page_size);
        $this->pager = new Pager(array('total'    => $this->num,
                                       'perpage'  => $this->page_size,
                                       'nowindex' => $this->page
        ));
    }

    /**
     * 检查SQL参数是否安全（有特殊字符）
     * @param $sql_sub
     * @throws SQLException
     */
    public static function sql_safe($sql_sub)
    {
        if (!preg_match(self::$allow_regx, $sql_sub)) {
            if (self::$error_call === '') {
                throw new SQLException("sql block '{$sql_sub}' is unsafe!");
            } else {
                call_user_func(self::$error_call);
            }
        }
    }

    /**
     * 获取组合成的SQL语句字符串
     * @param $ifreturn
     * @return string | null
     */
    public function getsql($ifreturn = true)
    {
        $this->sql = "select {$this->select} from {$this->table}";
        $this->sql .= implode(
            ' ',
            array(
                $this->join,
                $this->use_index,
                $this->where,
                $this->union,
                $this->group,
                $this->having,
                $this->order,
                $this->limit,
                $this->for_update,
            )
        );

        if ($this->if_union) {
            $this->sql = str_replace('{#union_select#}', $this->union_select, $this->sql);
        }
        if ($ifreturn) {
            return $this->sql;
        }
    }

    public function raw_put($params)
    {
        foreach ($params as $array) {
            if (isset($array[0]) and isset($array[1]) and count($array) == 2) {
                $this->_call($array[0], $array[1]);
            } else {
                $this->raw_put($array);
            }
        }
    }

    /**
     * 锁定行或表
     * @return null
     */
    public function lock()
    {
        $this->for_update = 'for update';
    }

    /**
     * 执行生成的SQL语句
     * @param $sql
     * @return null
     */
    public function exeucte($sql = '')
    {
        if ($sql == '') {
            $this->getsql(false);
        } else {
            $this->sql = $sql;
        }
        $this->result = $this->db->query($this->sql);
        $this->is_execute++;
    }

    /**
     * SQL联合
     * @param $sql
     * @return null
     */
    public function union($sql)
    {
        $this->if_union = true;
        if ($sql instanceof SelectDB) {
            $this->union_select = $sql->select;
            $sql->select = '{#union_select#}';
            $this->union = 'UNION ('.$sql->getsql(true).')';
        } else {
            $this->union = 'UNION ('.$sql.')';
        }
    }

    protected function _where($param)
    {
        if (is_array($param)) {
            return call_user_func_array(array($this, 'where'), $param);
        } else {
            return call_user_func(array($this, 'where'), $param);
        }
    }

    /**
     * 将数组作为指令调用
     * @param $params
     * @return null
     */
    public function put($params)
    {
        if (isset($params['put'])) {
            Error::info('SelectDB Error!', 'Params put() cannot call put()!');
        }
        //处理where条件
        if (isset($params['where'])) {
            $wheres = $params['where'];
            if (is_array($wheres)) {
                foreach ($wheres as $where) {
                    $this->_where($where);
                }
            } else {
                $this->_where($wheres);
            }
            unset($params['where']);
        }
        //处理orwhere条件
        if (isset($params['orwhere'])) {
            $orwheres = $params['orwhere'];
            if (is_array($orwheres)) {
                foreach ($orwheres as $orwhere) {
                    $this->orwhere($orwhere);
                }
            } else {
                $this->$orwheres($orwheres);
            }
            unset($params['orwhere']);
        }
        //处理walk调用
        if (isset($params['walk'])) {
            foreach ($params['walk'] as $call) {
                list($key, $value) = each($call);
                if (strpos($key, '_') !== 0) {
                    $this->_call($key, $value);
                } else {
                    $this->extraParmas[substr($key, 1)] = $value;
                }
            }
            unset($params['walk']);
        }
        //处理其他参数
        foreach ($params as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $this->_call($key, $value);
            } else {
                $this->extraParmas[substr($key, 1)] = $value;
            }
        }
    }

    /**
     * @param $method
     * @param $param
     * @return bool
     */
    protected function _call($method, $param)
    {
        if ($method == 'update' or $method == 'delete' or $method == 'insert') {
            return false;
        }

        //调用对应的方法
        if (method_exists($this, $method)) {
            if (is_array($param)) {
                call_user_func_array(array($this, $method), $param);
            } else {
                $this->$method($param);
            }
        }
        //直接将Key作为条件
        else {
            $param = $this->db->quote($param);
            if ($this->call_by == 'func') {
                $this->where($method . '="' . $param . '"');
            } elseif ($this->call_by == 'smarty') {
                if (strpos($param, '$') === false) {
                    $this->where($method . "='" . $param . "'");
                } else {
                    $this->where($method . "='{" . $param . "}'");
                }
            } else {
                Error::info('Error: SelectDB 错误的参数', "<pre>参数$method=$param</pre>");
            }
        }
        return true;
    }
    /**
     * 获取记录
     * @param $field
     * @return array
     */
    public function getone($field = '')
    {
        $this->limit('1');
        if ($this->auto_cache or !empty($cache_id)) {
            $cache_key = empty($cache_id) ? self::CACHE_PREFIX . '_one_' . md5($this->sql) : self::CACHE_PREFIX . '_all_' . $cache_id;
            $php = SPF\App::getInstance();
            ;
            $record = $php->cache->get($cache_key);
            if (empty($data)) {
                if ($this->is_execute == 0) {
                    $this->exeucte();
                }
                $record = $this->result->fetch();
                $php->cache->set($cache_key, $record, $this->cache_life);
            }
        } else {
            if ($this->is_execute == 0) {
                $this->exeucte();
            }
            $record = $this->result->fetch();
        }
        if ($field === '') {
            return $record;
        }
        return $record[$field];
    }

    protected function _execute()
    {
        if ($this->is_execute == 0) {
            $this->exeucte();
        }
        if (!is_bool($this->result)) {
            return $this->result->fetchall();
        } else {
            return false;
        }
    }

    /**
     * 获取所有记录
     * @return array | bool
     */
    public function getall()
    {
        //启用了Cache
        if ($this->enableCache) {
            $this->getsql(false);
            //指定Cache的Key
            if (empty($this->cacheOptions['key'])) {
                $cache_key = self::CACHE_PREFIX . '_all_' . md5($this->sql);
            } else {
                $cache_key = $this->cacheOptions['key'];
            }
            //指定使用哪个Cache实例
            if (empty($this->cacheOptions['object_id'])) {
                $cacheObject = App::getInstance()->cache;
            } else {
                $cacheObject = App::getInstance()->cache($this->cacheOptions['object_id']);
            }
            //指定缓存的生命周期
            if (empty($this->cacheOptions['lifetime'])) {
                $cacheLifeTime = self::CACHE_LIFETIME;
            } else {
                $cacheLifeTime = intval($this->cacheOptions['lifetime']);
            }

            $data = $cacheObject->get($cache_key);
            //Cache数据为空，从DB中拉取
            if (empty($data)) {
                $data = $this->_execute();
                $cacheObject->set($cache_key, $data, $cacheLifeTime);
                return $data;
            } else {
                return $data;
            }
        } else {
            return $this->_execute();
        }
    }

    /**
     * 获取当前条件下的记录数
     * @return int
     */
    public function count()
    {
        $sql = "select count({$this->count_fields}) as c from {$this->table} {$this->join} {$this->where} {$this->union} {$this->group}";

        if ($this->if_union) {
            $sql = str_replace('{#union_select#}', "count({$this->count_fields}) as c", $sql);
            $c = $this->db->query($sql)->fetchall();
            $cc = 0;
            foreach ($c as $_c) {
                $cc += $_c['c'];
            }
            $count =  intval($cc);
        } else {
            $_c = $this->db->query($sql);
            if ($_c === false) {
                return false;
            } else {
                $c = $_c->fetch();
            }
            $count = intval($c['c']);
        }
        return $count;
    }

    /**
     * 执行插入操作
     * @param $data
     * @return bool
     */
    public function insert($data)
    {
        $field = "";
        $values = "";

        foreach ($data as $key => $value) {
            $value = $this->db->quote($value);
            $field = $field . "`$key`,";
            $values = $values . "'$value',";
        }

        $field = substr($field, 0, -1);
        $values = substr($values, 0, -1);
        return $this->db->query("insert into {$this->table} ($field) values($values)");
    }

    /**
     * 批量插入
     * @param array $fields
     * @param array $data
     * @return Database\MySQLiRecord
     */
    public function insertBatch(array $fields, array $data)
    {
        $field_str = "";
        $c1 = count($fields) - 1;
        $c2 = count($data) - 1;


        /**
         * 拼接字段
         */
        foreach ($fields as $k => $f) {
            $field_str .= '`' . $f . '`';
            if ($k < $c1) {
                $field_str .= ', ';
            }
        }

        $sql = 'INSERT INTO ' . $this->table . '(' . $field_str . ') VALUES ';

        foreach ($data as $k => $v) {
            $sql .= '(';
            foreach ($v as $k2 => $v2) {
                $v2 = $this->db->quote($v2);
                $sql .= "'$v2'";
                if ($k2 < $c1) {
                    $sql .= ", ";
                }
            }

            $sql .= ')';
            if ($k < $c2) {
                $sql .= ', ';
            }
        }
        return $this->db->query($sql);
    }

    /**
     * 对符合当前条件的记录执行update
     * @param $data
     * @return bool
     */
    public function update($data)
    {
        $update = "";
        foreach ($data as $key => $value) {
            $value = $this->db->quote($value);
            if ($value != '' and $value[0] == '`') {
                $update = $update . "`$key`=$value,";
            } else {
                $update = $update . "`$key`='$value',";
            }
        }
        $update = substr($update, 0, -1);
        return $this->db->query("update {$this->table} set $update {$this->where} {$this->limit}");
    }

    /**
     * 删除当前条件下的记录
     * @return bool
     */
    public function delete()
    {
        return $this->db->query("delete from {$this->table} {$this->where} {$this->limit}");
    }

    /**
     * 获取受影响的行数
     * @return int
     */
    public function rowCount()
    {
        return $this->db->getAffectedRows();
    }
}
