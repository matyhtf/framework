<?php
namespace SPF;

/**
 * 表单处理器
 * 用于生成HTML表单项
 * @author Tianfeng.Han
 * @package SwooleSystem
 * @subpackage HTML
 * @link http://www.swoole.com/
 *
 */
class Form
{
    public static $checkbox_value_split = ',';
    public static $default_help_option = '请选择';

    /**
     * 根据数组，生成表单
     * @param $form_array
     * @return unknown_type
     */
    public static function autoform($form_array)
    {
        $forms = array();
        foreach ($form_array as $k=>$v) {
            //表单类型
            $func = $v['type'];
            //表单值
            $value = '';
            if (isset($v['value'])) {
                $value = $v['value'];
            }
            unset($v['type'],$v['value']);

            if ($func=='input' or $func=='password' or $func=='text' or $func=='htmltext') {
                $forms[$k] = self::$func($k, $value, $v);
            } else {
                $option = $v['option'];
                $self = $v['self'];
                $label_class = $v['label_class'];
                unset($v['option'],$v['self'],$v['label_class']);
                $forms[$k] = self::$func($k, $option, $value, $self, $v, $label_class);
                if ($func=='radio' and isset($v['empty'])) {
                    $forms[$k].= "\n<script language='javascript'>add_filter('{$k}','{$v['empty']}',function(){return getRadioValue('{$k}');});</script>";
                } elseif ($func=='checkbox' and isset($v['empty'])) {
                    $forms[$k].= "\n<script language='javascript'>add_filter('{$k}[]','{$v['empty']}',function(){return getCheckboxValue('{$k}[]');});</script>";
                }
            }
        }
        return $forms;
    }
    public static function checkInput($input, $form, &$error)
    {
        foreach ($form as $name=>$f) {
            $value = $input[$name];
            // 为空的情况 -empty
            if (isset($f['empty']) and empty($value)) {
                $error = $f['empty'];
                return false;
            }
            //检测字符串最大长度
            if (isset($f['maxlen'])) {
                $qs = explode('|', $f['maxlen']);
                if (mb_strlen($value)>$qs[0]) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检测字符串最小长度
            if (isset($f['minlen'])) {
                $qs = explode('|', $f['maxlen']);
                if (mb_strlen($value)>$qs[0]) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检查数值相等的情况 -equal
            if (isset($f['equal'])) {
                $qs = explode('|', $f['equal']);
                if ($value!=$qs[0]) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检查数值相等的情况 -noequal
            if (isset($f['noequal'])) {
                $qs = explode('|', $f['noequal']);
                if ($value==$qs[0]) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检查对象相等的情况 -equalo
            if (isset($f['equalo'])) {
                $qs = explode('|', $f['equalo']);
                if ($value==$input[$qs[0]]) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检查对象相等的情况 -equalo
            if (isset($f['ctype'])) {
                $qs = explode('|', $f['ctype']);
                if (!Validate::check($qs[0], $value)) {
                    $error = $qs[1];
                    return false;
                }
            }
            //检查值的类型 -regx，自定义正则检查
            if (isset($f['regx'])) {
                $qs = explode('|', $f['regx']);
                if (!Validate::regx($qs[0], $value)) {
                    $error = $qs[1];
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * 元素选项处理
     * @param $attr
     * @return unknown_type
     */
    public static function input_attr(&$attr)
    {
        $str = " ";
        if (!empty($attr) && is_array($attr)) {
            foreach ($attr as $key=>$value) {
                $str .= "$key=\"$value\" ";
            }
        }
        return $str;
    }
    /**
     * 下拉选择菜单
     * $name  此select 的 name 标签
     * $array 要制作select 的数
     * $default 如果要设定默认选择哪个数据 就在此填入默认的数据的值
     * $self 设置为true，option的值等于$value
     * $attrArray html标签的熟悉  就是这个select的属性标签 例如  class="x1"
     * $add_help 增加一个值为空的 请选择 项
     * $force　强类型判断
     */
    public static function select($name, $option, $default = null, $self = null, $attrArray = null, $add_help = true, $force = false)
    {
        $htmlStr = "<select name=\"$name\" id=\"$name\"";
        $htmlStr .= self::input_attr($attrArray) . ">\n";

        if ($add_help) {
            if ($add_help === true) {
                $htmlStr .= "<option value=\"\">" . self::$default_help_option . "</option>\n";
            } else {
                $htmlStr .= "<option value=\"\">$add_help</option>\n";
            }
        }
        foreach ($option as $key => $value) {
            if ($self) {
                $key = $value;
            }

            if (!$force and $key == $default) {
                $htmlStr .= "<option value=\"{$key}\" selected=\"selected\">{$value}</option>\n";
            } elseif ($force and $key === $default) {
                $htmlStr .= "<option value=\"{$key}\" selected=\"selected\">{$value}</option>\n";
            } else {
                $htmlStr .= "<option value=\"{$key}\">{$value}</option>\n";
            }
        }
        $htmlStr .= "</select>\n";

        return $htmlStr;
    }

    /**
     * 多选下拉选择菜单
     * $name  此select 的 name 标签
     * $array 要制作select 的数
     * $default 如果要设定默认选择哪个数据 就在此填入默认的数据的值
     * $self 设置为ture，option的值等于$value
     * $attrArray html标签的熟悉  就是这个select的属性标签 例如  class="x1"
     * $add_help 增加一个值为空的 请选择 项
     */
    public static function muti_select($name, $option, $default=array(), $self=null, $attrArray=null, $add_help=true)
    {
        $htmlStr = "<select name=\"$name\" id=\"$name\"";
        $htmlStr .= self::input_attr($attrArray) . ">\n";

        if ($add_help) {
            if ($add_help===true) {
                $htmlStr .= "<option value=\"\">".self::$default_help_option."</option>\n";
            } else {
                $htmlStr .= "<option value=\"\">$add_help</option>\n";
            }
        }
        foreach ($option as $key => $value) {
            if ($self) {
                $key=$value;
            }
            if (in_array($key, $default)) {
                $htmlStr .= "<option value=\"{$key}\" selected=\"selected\">{$value}</option>\n";
            } else {
                $htmlStr .= "<option value=\"{$key}\">{$value}</option>\n";
            }
        }
        $htmlStr .= "</select>\n";

        return $htmlStr;
    }

    /**
     * 单选按钮
     *	$name  此radio 的 name 标签
     *	$array 要制作radio 的数
     *	$default 如果要设定默认选择哪个数据 就在此填入默认的数据的值
     *	$self 设置为ture，option的值等于$value
     *	$attrArray html的属性 例如  class="x1"
     **/
    public static function radio($name, $option, $default = null, $self = false, $attrArray = null, $label_class = '')
    {
        $htmlStr = "";
        $attrStr = self::input_attr($attrArray);

        foreach ($option as $key => $value) {
            if ($self) {
                $key=$value;
            }
            if ($key == $default) {
                $htmlStr .= "<label class='$label_class'><input type=\"radio\" name=\"$name\" id=\"{$name}_{$key}\" value=\"$key\" checked=\"checked\" {$attrStr} />".$value."</label>";
            } else {
                $htmlStr .= "<label class='$label_class'><input type=\"radio\" name=\"$name\" id=\"{$name}_{$key}\" value=\"$key\"  {$attrStr} />&nbsp;".$value."</label>";
            }
        }
        return $htmlStr;
    }
    /**
     * 多选按钮
     * @param string $name  此radio 的 name 标签
     * @param array $option 要制作radio 的数
     * @param string $default 如果要设定默认选择哪个数据 就在此填入默认的数据的值
     * @param bool $self 设置为ture，option的值等于$value
     * @param array $attrArray html的属性 例如  class="x1"
     * @param string $label_class
     * @return string
     */
    public static function checkbox($name, $option, $default = null, $self = false, $attrArray = null, $label_class = '')
    {
        $htmlStr = "";
        $attrStr = self::input_attr($attrArray);
        $default = array_flip(explode(self::$checkbox_value_split, $default));

        foreach ($option as $key => $value) {
            if ($self) {
                $key = $value;
            }
            if (isset($default[$key])) {
                $htmlStr
                    .=
                    "<label class='$label_class'><input type=\"checkbox\" name=\"{$name}[]\" id=\"{$name}_$key\" value=\"$key\" checked=\"checked\" {$attrStr} />"
                    . $value . '</label>';
            } else {
                $htmlStr
                    .=
                    "<label class='$label_class'><input type=\"checkbox\" name=\"{$name}[]\" id=\"{$name}_$key\" value=\"$key\"  {$attrStr} />"
                    . $value . '</label>';
            }
        }
        return $htmlStr;
    }

    /**
     * 文件上传表单
     * @param $name 表单名称
     * @param $value 值
     * @param $attrArray html的属性 例如  class="x1"
     * @return unknown_type
     */
    public static function upload($name, $value = '', $attrArray = null)
    {
        $attrStr = self::input_attr($attrArray);
        $form = '';
        if (!empty($value)) {
            $form = " <a href='$value' target='_blank'>查看文件</a><br />\n重新上传";
        }
        return $form."<input type='file' name='$name' id='{$name}' {$attrStr} />";
    }
    /**
     * 单行文本输入框
     * @param $name
     * @param $value
     * @param $attrArray
     * @return string
     */
    public static function input($name, $value = '', $attrArray = null)
    {
        $attrStr = self::input_attr($attrArray);
        return "<input type='text' name='{$name}' id='{$name}' value='{$value}' {$attrStr} />";
    }
    /**
     * 按钮
     * @param $name
     * @param $value
     * @param $attrArray
     * @return unknown_type
     */
    public static function button($name, $value='', $attrArray=null)
    {
        if (empty($attrArray['type'])) {
            $attrArray['type'] = 'button';
        }
        $attrStr = self::input_attr($attrArray);
        return "<input name='{$name}' id='{$name}' value='{$value}' {$attrStr} />";
    }
    /**
     * 密码输入框
     * @param $name
     * @param $value
     * @param $attrArray
     * @return unknown_type
     */
    public static function password($name, $value='', $attrArray=null)
    {
        $attrStr = self::input_attr($attrArray);
        return "<input type='password' name='{$name}' id='{$name}' value='{$value}' {$attrStr} />";
    }
    /**
     * 多行文本输入框
     * @param $name
     * @param $value
     * @param $attrArray
     * @return string
     */
    public static function text($name, $value='', $attrArray=null)
    {
        if (!isset($attrArray['cols'])) {
            $attrArray['cols'] = 60;
        }
        if (!isset($attrArray['rows'])) {
            $attrArray['rows'] = 3;
        }
        $attrStr = self::input_attr($attrArray);
        $forms = "<textarea name='{$name}' id='{$name}' $attrStr>$value</textarea>";
        return $forms;
    }

    /**
     * 隐藏项
     * @param $name
     * @param $value
     * @param $attrArray
     * @return string
     */
    public static function hidden($name, $value='', $attrArray=null)
    {
        $attrStr = self::input_attr($attrArray);
        return "<input type='hidden' name='{$name}' id='{$name}' value='{$value}' {$attrStr} />";
    }

    /**
     * 表单头部
     * @param $form_name
     * @param $method
     * @param $action
     * @param $if_upload
     * @param $attrArray
     * @return string
     */
    public static function head($form_name, $method='post', $action='', $if_upload=false, $attrArray=null)
    {
        if ($if_upload) {
            $attrArray['enctype'] = "multipart/form-data";
        }
        $attrStr = self::input_attr($attrArray);
        return "action='$action' method='$method' name='$form_name' id='$form_name' $attrStr";
    }
    /**
     * 设置Form Secret防止，非当前页面提交数据
     * @param $page_name
     * @param $return
     * @return string
     */
    public static function secret($page_name = '', $length = 32, $return = false)
    {
        $secret = uniqid(RandomKey::string($length));
        if ($return) {
            return $secret;
        } else {
            $k = 'form_' . $page_name;
            setcookie($k, $secret, 0, '/');
            if (!isset($_SESSION)) {
                session();
            }
            $_SESSION[$k] = $secret;
        }
    }
}
