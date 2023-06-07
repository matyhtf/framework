<?php

namespace SPF\Struct;

use Iterator;
use Countable;
use SPF\Exception\NotFoundException;
use SPF\Exception\Exception;

class Lists extends BaseStruct implements Iterator, Countable
{
    /**
     * List items
     *
     * @var array
     */
    protected $items = [];

    /**
     * @var int
     */
    protected $pos = 0;

    /**
     * ListItem type
     *
     * @var string
     */
    protected $type = null;

    /**
     * ListItem type whether build in
     *
     * @var bool
     */
    protected $typeBuildIn = false;

    /**
     * Function gettype return value and standard type map
     *
     * @var array
     */
    protected $typeMap = [
        'boolean' => 'bool',
        'integer' => 'int',
        'double' => 'float',
        'string' => 'string',
        'array' => 'array',
        'object' => 'object',
        'resource' => 'resource',
        'NULL' => 'null',
    ];

    /**
     * @param string $type ListItem struct type
     * @param array $items
     */
    public function __construct(string $type, array $items = [])
    {
        $this->initType($type);

        $this->replace($items);
    }

    /**
     * @param string $type
     *
     * @throw Exception
     */
    private function initType(string $type)
    {
        if (in_array($type, ['int', 'bool', 'string', 'float'])) {
            $this->type = $type;
            $this->typeBuildIn = true;
        } elseif (class_exists($type)) {
            $this->type = $type;
            $this->typeBuildIn = false;
        } else {
            throw new Exception("list数据类型不合法");
        }
    }

    /**
     * @param mixed $item
     */
    protected function validItem($item)
    {
        if ($this->typeBuildIn) {
            $type = gettype($item);
            if (!isset($this->typeMap[$type])) {
                throw new Exception("item数据类型不合法");
            }
            if ($this->typeMap[$type] !== $this->type) {
                throw new Exception("item数据类型不合法");
            }
        } else {
            if (!is_object($item) || get_class($item) !== $this->type) {
                throw new Exception("item数据类型不合法");
            }
        }
    }

    /**
     * @var mixed $item
     *
     * @return self
     */
    public function append($item)
    {
        $this->validItem($item);

        array_push($this->items, $item);

        return $this;
    }

    /**
     * @param mixed $item
     *
     * @return self
     */
    public function push($item)
    {
        return $this->append($item);
    }

    /**
     * @param mixed $item
     *
     * @return self
     */
    public function prepend($item)
    {
        $this->validItem($item);

        array_unshift($this->items, $item);

        return $this;
    }

    /**
     * @param mixed $item
     *
     * @return self
     */
    public function unshift($item)
    {
        return $this->prepend($item);
    }

    /**
     * @param int $index
     *
     * @return mixed
     */
    public function get(int $index)
    {
        if (!isset($this->items[$index])) {
            throw new NotFoundException("索引 {$index} 不存在");
        }

        return $this->items[$index];
    }

    /**
     * Replace all items.
     *
     * @param array $items
     *
     * @return self
     */
    public function replace(array $items)
    {
        foreach ($items as $item) {
            $this->validItem($item);
        }

        $this->items = $items;

        return $this;
    }

    /**
     * @return string
     */
    public function getItemType()
    {
        return $this->type;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->items;
    }

    public function rewind()
    {
        $this->pos = 0;
    }

    public function current()
    {
        return $this->items[$this->pos];
    }

    public function key()
    {
        return $this->pos;
    }

    public function next()
    {
        ++$this->pos;
    }

    public function valid()
    {
        return isset($this->items[$this->pos]);
    }

    public function __set($key, $value)
    {
        throw new PropertyNotAllowedException(__CLASS__, $key, 'not allowed set if the class not exists the property');
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }
}
