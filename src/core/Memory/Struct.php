<?php
namespace SPF\Memory;

use SPF\Exception\InvalidParam;

/**
 * C语言struct操作封装类，注意C语言存在内存对齐问题，编译C程序使用 pack(1)
 * 子类的属性增加注释 [@fieldtype $type]，请注意不能开启opcache的注释过滤功能，否则将无法解析
 * 目前支持的类型：
 * int, int8, int16, int32, int64, uint, uint8, uint16, uint32, uint64, long, ulong
 * float, double
 * char[n], uchar[n]
 * @package SPF\Memory
 * @author Tianfeng.Han (Rango)
 */
abstract class Struct
{
    protected $size = 0;
    protected $fileds = array();
    protected $is32bit;
    protected $class;
    /**
     * 关联数组模式
     */
    protected $assoc;

    /**
     * 主机字节序或者网络字节序
     */
    protected $convertBigEndian;

    const REGX_FIELDTYPE = '#@fieldtype\s+([a-z0-9]+(\[[a-z0-9_\\\]+\])?)\s+#i';
    const REGX_FILEINFO = '#\[([a-z0-9_\\\]+)\]#i';

    /**
     * @param bool $convertBigEndian 整形全部转换为大端网络字节序，默认为主机字节序
     * @param bool $assoc 是否使用关联数组
     * @throws InvalidParam
     */
    function __construct($convertBigEndian = true, $assoc = false)
    {
        $this->is32bit = (PHP_INT_SIZE === 4);
        $this->convertBigEndian = $convertBigEndian;
        $this->class = get_class($this);
        $this->assoc = $assoc;
        $rClass = new \ReflectionClass($this->class);
        $props = $rClass->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($props as $p)
        {
            if (preg_match(self::REGX_FIELDTYPE, $p->getDocComment(), $match))
            {
                $field = $this->parseFieldType($match[1]);
                $field->name = $p->getName();
                if ($this->assoc)
                {
                    $this->fileds[$field->name] = $field;
                }
                else
                {
                    $this->fileds[] = $field;
                }
                $this->size += $field->size;
            }
        }
    }

    /**
     * @return int
     */
    function size()
    {
        return $this->size;
    }

    /**
     * @param $fieldType
     * @return Field
     * @throws InvalidParam
     */
    protected function parseFieldType($fieldType)
    {
        $signed = false;
        $struct = null;

        start_switch:
        switch (strtolower($fieldType[0]))
        {
            case 'u':
                $signed = true;
                $fieldType = substr($fieldType, 1);
                goto start_switch;

            case 'i':
                if ($fieldType == 'int')
                {
                    $size = 4;
                }
                else
                {
                    $size = substr($fieldType, 3) / 8;
                }
                $type = Field::INT;
                break;

            case 'l':
                $size = $this->is32bit ? 4 : 8;
                $type = Field::INT;
                break;

            case 'f':
                $size = 4;
                $type = Field::FLOAT;
                break;

            case 'd':
                $size = $this->is32bit ? 4 : 8;
                $type = Field::FLOAT;
                break;

            case 'c':
                $size = intval(substr($fieldType, 5));
                $type = Field::CHAR;
                break;

            /**
             * 嵌套结构体
             */
            case 's':
                if (preg_match(self::REGX_FILEINFO, $fieldType, $match))
                {
                    $class = '\\'.$match[1];
                    /**
                     * @var $struct Struct
                     */
                    $struct = new $class($this->convertBigEndian, $this->assoc);
                    $type = Field::STRUCT;
                    $size = $struct->size();
                }
                else
                {
                    throw new InvalidParam("require struct class name.");
                }
                break;
            default:
                throw new InvalidParam("invalid field type [{$fieldType[0]}].");
        }

        $field = new Field();
        $field->size = $size;
        $field->signed = $signed;
        $field->type = $type;
        $field->struct = $struct;
        $field->fieldType = $fieldType;
        return $field;
    }

    /**
     * 打包数据
     * @param array $data
     * @return string
     * @throws InvalidParam
     */
    function pack(array $data)
    {
        if (count($data) != count($this->fileds))
        {
            throw new InvalidParam("{$this->class}: invalid data.");
        }

        $_binStr = '';
        foreach ($this->fileds as $k => $field)
        {
            if (!isset($data[$k]))
            {
                throw new InvalidParam("{$this->class}: item[key=$k] is not exists.");
            }
            /**
             * @var $field Field
             */
            switch ($field->type)
            {
                case Field::INT:
                    $value = intval($data[$k]);
                    switch ($field->size)
                    {
                        case 1:
                            $_binStr .= pack($field->signed ? 'c' : 'C', $value);
                            break;
                        case 2:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                $_binStr .= pack('n', $value);
                            }
                            else
                            {
                                $_binStr .= pack($field->signed ? 's' : 'S', $value);
                            }
                            break;
                        case 4:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                $_binStr .= pack('N', $value);
                            }
                            else
                            {
                                $_binStr .= pack($field->signed ? 'l' : 'L', $value);
                            }
                            break;
                        case 8:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                $_binStr .= pack('J', $value);
                            }
                            else
                            {
                                $_binStr .= pack($field->signed ? 'q' : 'Q', $value);
                            }
                            break;
                        default:
                            break;
                    }
                    break;
                case Field::FLOAT:
                    $value = floatval($data[$k]);
                    $_binStr .= pack($field->size == 4 ? 'f' : 'd', $value);
                    break;
                case Field::CHAR:
                    $value = strval($data[$k]);
                    //C字符串末尾必须为\0，最大只能保存(size-1)个字节
                    if (strlen($value) > $field->size - 1)
                    {
                        throw new InvalidParam("string is too long.");
                    }
                    $_binStr .=  pack('a' . ($field->size - 1) . 'x', $value);
                    break;
                /**
                 * 结构体类型
                 */
                case Field::STRUCT:
                    //参数必须为数组，个数必须与结构体的字段数量一致
                    if (!is_array($data[$k]) or count($data[$k]) != count($field->struct->fileds))
                    {
                        throw new InvalidParam("struct size invalid.");
                    }
                    $_binStr .= $field->struct->pack($data[$k]);
                    break;
                default:
                    break;
            }
        }

        return $_binStr;
    }

    /**
     * 解包数据
     * @param $str
     * @return array
     */
    function unpack($str)
    {
        $data = array();
        foreach ($this->fileds as $k => $field)
        {
            /**
             * @var $field Field
             */
            switch ($field->type)
            {
                case Field::INT:
                    switch ($field->size)
                    {
                        case 1:
                            list(, $data[$k]) = unpack($field->signed ? 'c' : 'C', $str);
                            break;
                        case 2:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                list(, $data[$k]) = unpack('n', $str);
                            }
                            else
                            {
                                list(, $data[$k]) = unpack($field->signed ? 's' : 'S', $str);
                            }
                            break;
                        case 4:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                list(, $data[$k]) = unpack('N', $str);
                            }
                            else
                            {
                                list(, $data[$k]) = unpack($field->signed ? 'l' : 'L', $str);
                            }
                            break;
                        case 8:
                            if ($this->convertBigEndian)
                            {
                                //网络字节序只有无符号的编码方式
                                list(, $data[$k]) = unpack('J', $str);
                            }
                            else
                            {
                                list(, $data[$k]) = unpack($field->signed ? 'q' : 'Q', $str);
                            }
                            break;
                        default:
                            break;
                    }
                    break;
                case Field::FLOAT:
                    list(, $data[$k]) = unpack($field->size == 4 ? 'f' : 'd', $str);
                    break;
                case Field::CHAR:
                    $data[$k] = substr($str, 0, strpos($str, "\0"));
                    break;
                case Field::STRUCT:
                    $data[$k] = $field->struct->unpack($str);
                    break;
                default:
                    break;
            }
            $str = substr($str, $field->size);
        }
        return $data;
    }
}

class Field
{
    public $name;
    public $type;
    public $size;
    public $signed;
    public $fieldType;

    /**
     * @var Struct
     */
    public $struct;

    const INT = 1;
    const FLOAT = 2;
    const CHAR = 3;
    const STRUCT = 4;
}