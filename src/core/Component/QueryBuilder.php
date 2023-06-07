<?php
namespace SPF\Component;

use SPF\Database;
use SPF\SelectDB;

class QueryBuilder
{
    protected $db;
    protected $selector;

    public function __construct(Database $db, $table, $fields)
    {
        $this->db = $db;
        $this->selector = new SelectDB($db);
        $this->selector->from($table);
        $this->selector->select($fields);
    }

    /**
     * @params $field
     * $params $expression
     * $params $value
     * @return $this
     */
    public function where()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc == 3) {
            $this->selector->where($args[0], $args[1], $args[2]);
        } elseif ($argc == 2) {
            $this->selector->equal($args[0], $args[1]);
        } else {
            if (is_array($args[0])) {
                foreach ($args[0] as $k => $v) {
                    $this->selector->equal($k, $v);
                }
            } else {
                $this->selector->where($args[0]);
            }
        }

        return $this;
    }

    /**
     * @param $order
     * @return $this
     */
    public function order($order)
    {
        $this->selector->order($order);

        return $this;
    }

    /**
     * @param $field
     * @param $like
     * @return $this
     */
    public function like($field, $like)
    {
        $this->selector->like($field, $like);

        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function in($field, $value)
    {
        $this->selector->in($field, $value);

        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return $this
     */
    public function limit($limit, $offset = -1)
    {
        if ($offset > 0) {
            $this->selector->limit($offset . ', ', $limit);
        } else {
            $this->selector->limit($limit);
        }

        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function notIn($field, $value)
    {
        $this->selector->notin($field, $value);

        return $this;
    }

    /**
     * @params $field
     * $params $expression
     * $params $value
     * @return $this
     */
    public function orWhere()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc == 3) {
            $this->selector->orwhere($args[0], $args[1], $args[2]);
        } elseif ($argc == 2) {
            $this->selector->orwhere($args[0], '=', $args[1]);
        } else {
            $this->selector->orwhere($args[0]);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function fetch()
    {
        return $this->selector->getone();
    }

    /**
     * @return array|bool
     */
    public function fetchAll()
    {
        return $this->selector->getall();
    }

    /**
     * @return null|string
     */
    public function getSql()
    {
        return $this->selector->getsql();
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    public function equal($field, $value)
    {
        $this->selector->equal($field, $value);
        return $this;
    }

    public function groupBy($field)
    {
        $this->selector->group($field);
        return $this;
    }

    public function join($table_name, $on)
    {
        $this->selector->join($table_name, $on);

        return $this;
    }

    public function leftJoin($table_name, $on)
    {
        $this->selector->leftJoin($table_name, $on);

        return $this;
    }

    public function rightJoin($table_name, $on)
    {
        $this->selector->rightJoin($table_name, $on);

        return $this;
    }

    public function find($field, $find)
    {
        $this->selector->find($field, $find);

        return $this;
    }

    public function having($expr)
    {
        $this->selector->having($expr);

        return $this;
    }

    /**
     * @return \SPF\Pager
     */
    public function getPager()
    {
        return $this->selector->pager;
    }

    public function paginate($page, $pagesize = 10)
    {
        $this->selector->page($page);
        $this->selector->pagesize($pagesize);
        $this->selector->paging();

        return $this;
    }
}
