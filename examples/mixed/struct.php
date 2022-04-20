<?php
define('DEBUG', 'on');
define('WEBPATH', realpath(__DIR__ . '/..'));
//包含框架入口文件
require WEBPATH . '/libs/lib_config.php';

class BStruct extends SPF\Memory\Struct
{
    /**
     * @fieldtype char[64]
     */
    public $key;

    /**
     * @fieldType int16
     */
    public $num;
}

class AStruct extends SPF\Memory\Struct
{
    /**
     * @fieldtype int32
     */
    public $id;

    /**
     * @fieldtype char[40]
     */
    public $data;

    /**
     * @fieldtype double
     */
    public $price;

    /**
     * @fieldtype float
     */
    public $price2;

    /**
     * @fieldtype struct[BStruct]
     */
    public $b;

    /**
     * @fieldtype int64
     */
    public $count;
}

$a = new AStruct(false, true);
$n = 1;
$s = microtime(true);
for ($i = 0; $i < $n; $i++)
{
    $str = $a->pack(array(
        'id' => 13,
        'data' => 'hello world',
        'price' => 999.9,
        'price2' => 888.8,
        'b' => array('key' => 'redis', 'num' => 5566,),
        'count' => 99999,
    ));
}
echo "$n pack, cost time ".(microtime(true) - $s)."s\n";

var_dump(strlen($str));

$result = $a->unpack($str);
var_dump($result);
