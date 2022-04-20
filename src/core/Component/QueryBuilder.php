<?php
namespace SPF\Component;

use SPF\Database;
use SPF\SelectDB;

class QueryBuilder
{
    protected $db;
    protected $selector;

    function __construct(Database $db, $table, $fields)
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
    function where()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc == 3)
        {
            $this->selector->where($args[0], $args[1], $args[2]);

        }
        elseif ($argc == 2)
        {
            $this->selector->equal($args[0], $args[1]);
        }
        else
        {
            if (is_array($args[0]))
            {
                foreach($args[0] as $k => $v)
                {
                    $this->selector->equal($k, $v);
                }
            }
            else
            {
                $this->selector->where($args[0]);
            }
        }

        return $this;
    }

    /**
     * @param $order
     * @return $this
     */
    function order($order)
    {
        $this->selector->order($order);

        return $this;
    }

    /**
     * @param $field
     * @param $like
     * @return $this
     */
    function like($field, $like)
    {
        $this->selector->like($field, $like);

        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    function in($field, $value)
    {
        $this->selector->in($field, $value);

        return $this;
    }

    /**
     * @param $limit
     * @param int $offset
     * @return $this
     */
    function limit($limit, $offset = -1)
    {
        if ($offset > 0)
        {
            $this->selector->limit($offset . ', ', $limit);
        }
        else
        {
            $this->selector->limit($limit);
        }

        return $this;
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    function notIn($field, $value)
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
    function orWhere()
    {
        $args = func_get_args();
        $argc = count($args);
        if ($argc == 3)
        {
            $this->selector->orwhere($args[0], $args[1], $args[2]);

        }
        elseif ($argc == 2)
        {
            $this->selector->orwhere($args[0], '=', $args[1]);
        }
        else
        {
            $this->selector->orwhere($args[0]);
        }

        return $this;
    }

    /**
     * @return array
     */
    function fetch()
    {
        return $this->selector->getone();
    }

    /**
     * @return array|bool
     */
    function fetchAll()
    {
        return $this->selector->getall();
    }

    /**
     * @return null|string
     */
    function getSql()
    {
        return $this->selector->getsql();
    }

    /**
     * @param $field
     * @param $value
     * @return $this
     */
    function equal($field, $value)
    {
        $this->selector->equal($field, $value);
        return $this;
    }

    function groupBy($field)
    {
        $this->selector->group($field);
        return $this;
    }

    function join($table_name, $on)
    {
        $this->selector->join($table_name, $on);

        return $this;
    }

    function leftJoin($table_name, $on)
    {
        $this->selector->leftJoin($table_name, $on);

        return $this;
    }

    function rightJoin($table_name, $on)
    {
        $this->selector->rightJoin($table_name, $on);

        return $this;
    }

    function find($field, $find)
    {
        $this->selector->find($field, $find);

        return $this;
    }

    function having($expr)
    {
        $this->selector->having($expr);

        return $this;
    }

    /**
     * @return \SPF\Pager
     */
    function getPager()
    {
        return $this->selector->pager;
    }

    function paginate($page, $pagesize = 10)
    {
        $this->selector->page($page);
        $this->selector->pagesize($pagesize);
        $this->selector->paging();

        return $this;
    }
}