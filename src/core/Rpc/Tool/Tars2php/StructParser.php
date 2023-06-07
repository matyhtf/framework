<?php

namespace SPF\Rpc\Tool\Tars2php;

class StructParser
{
    public $uniqueName;
    public $moduleName;
    public $structName;
    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $preStructs;
    public $preEnums;
    public $preNamespaceEnums;
    public $preNamespaceStructs;

    public $extraContructs = '';
    public $extraExtInit = '';

    public $consts = '';
    public $variables = '';
    public $fields = '';

    public $namespaceName;

    protected $config = [];
    protected $nsPrefix = '';

    public function __construct(
        $fp,
        $structName,
        $nsPrefix,
        $config,
        $preStructs,
        $preEnums,
        $preNamespaceEnums,
        $preNamespaceStructs
    ) {
        $this->fp = $fp;
        $this->structName = $structName;
        $this->nsPrefix = $nsPrefix;
        $this->config = $config;
        
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;

        $this->consts = '';
        $this->variables = '';
        $this->fields = '';
    }

    public function parse()
    {
        while ($this->state != 'end') {
            $this->structBodyParseLine();
        }

        // 先把积累下来的三个部分处理掉
        $structClassStr = $this->getStructClassHeader() .
            'class ' . $this->structName . " extends \TARS_Struct". Utils::lineFeed(1) ."{" . Utils::lineFeed(1);

        $structClassStr .= $this->consts . Utils::lineFeed(2);
        $structClassStr .= $this->variables . Utils::lineFeed(2);
        $fieldsPrefix = Utils::indent(1) . 'protected static $_fields = [' . Utils::lineFeed(1);
        $fieldsSuffix = Utils::indent(1) . '];' . Utils::lineFeed(2);

        $structClassStr .= $fieldsPrefix;
        $structClassStr .= $this->fields;
        $structClassStr .= $fieldsSuffix;

        // 处理最后一行

        $construct = Utils::indent(1) . 'public function __construct() ' . Utils::lineFeed(1)
            . Utils::indent(1) .'{' . Utils::lineFeed(1)
            . Utils::indent(2) . "parent::__construct('" . $this->getStructClassUniqueName($this->structName)
            . "', self::\$_fields);" . Utils::lineFeed(1)
            . $this->extraContructs
            . $this->extraExtInit
            . Utils::indent(1) . '}' . Utils::lineFeed(1);

        $structClassStr .= $construct . '}' . Utils::lineFeed(1);

        return $structClassStr;
    }

   

    /**
     * @param $startChar
     * @param $lineString
     *
     * @return string
     *                专门处理注释
     */
    public function copyAnnotation($startChar, $lineString)
    {
        $lineString .= $startChar;
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if ($nextChar == '/') {
            $lineString .= $nextChar;
            while (1) {
                $tmpChar = fgetc($this->fp);
                if (Utils::isReturn($tmpChar)) {
                    $this->state = 'lineEnd';
                    break;
                }
                $lineString .= $tmpChar;
            }

            return $lineString;
        } elseif ($nextChar == '*') {
            $lineString .= $nextChar;
            while (1) {
                $tmpChar = fgetc($this->fp);
                $lineString .= $tmpChar;

                if ($tmpChar === false) {
                    Utils::abnormalExit('error', '注释换行错误,请检查');
                } elseif (Utils::isReturn($tmpChar)) {
                } elseif (($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if ($nextnextChar == '/') {
                        $lineString .= $nextnextChar;

                        return $lineString;
                    } else {
                        $pos = ftell($this->fp);
                        fseek($this->fp, $pos - 1);
                    }
                }
            }
        }
        // 注释不正常
        else {
            Utils::abnormalExit('error', '注释换行错误,请检查');
        }
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     */
    public function structBodyParseLine()
    {
        $validLine = false;

        $this->state = 'init';

        $lineString = '';
        $word = '';
        $wholeType = '';
        $defaultValue = null;


        $mapVectorState = false;
        while (1) {
            $char = fgetc($this->fp);

            if ($this->state == 'init') {
                // 有可能是换行
                if (
                    $char == '{' || Utils::isSpace($char) || $char == '\r'
                    || $char == '\x0B' || $char == '\0'
                ) {
                    continue;
                } elseif ($char == "\n") {
                    break;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                elseif ($char == '/') {
                    $this->copyAnnotation($char, $lineString);
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $this->state = 'identifier';
                    $word .= $char;
                }
                // 终止条件之1,宣告struct结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                } elseif ($char == '=') {
                    //遇到等号,可以贪婪的向后,直到遇到;或者换行符
                    if (!empty($word)) {
                        $valueName = $word;
                    }
                    $moreChar = fgetc($this->fp);

                    $defaultValue = '';

                    while ($moreChar != '\n' && $moreChar != ';' && $moreChar != '}') {
                        $defaultValue .= $moreChar;

                        $moreChar = fgetc($this->fp);
                    }
                    //if(empty($defaultValue)) {
                    //    Utils::abnormalExit('error','结构体'.$this->structName.'内默认值格式错误,请更正tars');
                    //}

                    if ($moreChar == '}') {
                        // 需要贪心的读到"\n"为止
                        while (($lastChar = fgetc($this->fp)) != "\n") {
                            continue;
                        }
                        $this->state = 'end';
                    } else {
                        $this->state = 'init';
                    }
                } else {
                    //echo "char:".var_export($char,true);
                    //Utils::abnormalExit('error','结构体'.$this->structName.'内格式错误,请更正tars');
                    continue;
                }
            } elseif ($this->state == 'identifier') {
                $validLine = true;
                // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                if (Utils::isSpace($char)) {
                    if (Utils::isTag($word)) {
                        $tag = $word;
                        $this->state = 'init';
                        $word = '';
                    } elseif (Utils::isRequireType($word)) {
                        $requireType = $word;
                        $this->state = 'init';
                        $word = '';
                    } elseif ($word == 'unsigned') {
                        $word = $word . ' ';
                        continue;
                    } elseif (Utils::isBasicType($word)) {
                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    } elseif (Utils::isStruct($word, $this->preStructs)) {
                        $type = $word;
                        $this->state = 'init';
                        $word = '';
                    } elseif (Utils::isEnum($word, $this->preEnums)) {
                        $type = 'int';
                        $this->state = 'init';
                        $word = '';
                    }
                    // 增加对namespace的支持
                    elseif (in_array($word, $this->preNamespaceStructs)) {
                        $type = explode('::', $word);
                        $type = $type[1];
                        $this->state = 'init';
                        $word = '';
                    }
                    // 增加对namespace的支持
                    elseif (in_array($word, $this->preNamespaceEnums)) {
                        $type = 'int';
                        $this->state = 'init';
                        $word = '';
                    } elseif ($word == 'unsigned') {
                        $word = $word . ' ';
                        continue;
                    } else {
                        // 读到了vector和map中间的空格,还没读完
                        if ($mapVectorState) {
                            continue;
                        }
                        // 否则剩余的部分应该就是值和默认值
                        else {
                            if (!empty($word)) {
                                $valueName = $word;
                            }
                            $this->state = 'init';
                            $word = '';
                        }
                    }
                }
                // 标志着map和vector的开始,不等到'>'的结束不罢休
                // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                elseif ($char == '<') {
                    // 贪婪的向后,直到找出所有的'>'
                    $type = $word;
                    // 还会有一个wholeType,表示完整的部分
                    $mapVectorStack = [];
                    $wholeType = $type;
                    $wholeType .= '<';
                    array_push($mapVectorStack, '<');
                    while (!empty($mapVectorStack)) {
                        $moreChar = fgetc($this->fp);
                        $wholeType .= $moreChar;
                        if ($moreChar == '<') {
                            array_push($mapVectorStack, '<');
                        } elseif ($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                    }

                    $this->state = 'init';
                    $word = '';
                } elseif ($char == '=') {
                    //遇到等号,可以贪婪的向后,直到遇到;或者换行符
                    if (!empty($word)) {
                        $valueName = $word;
                    }
                    $moreChar = fgetc($this->fp);

                    $defaultValue = '';

                    while ($moreChar != '\n' && $moreChar != ';' && $moreChar != '}') {
                        $defaultValue .= $moreChar;

                        $moreChar = fgetc($this->fp);
                    }
                    //if(empty($defaultValue)) {
                    //    Utils::abnormalExit('error','结构体'.$this->structName.'内默认值格式错误,请更正tars');
                    //}

                    if ($moreChar == '}') {
                        // 需要贪心的读到"\n"为止
                        while (($lastChar = fgetc($this->fp)) != "\n") {
                            continue;
                        }
                        $this->state = 'end';
                    } else {
                        $this->state = 'init';
                    }
                } elseif ($char == ';') {
                    if (!empty($word)) {
                        $valueName = $word;
                    }
                    continue;
                }
                // 终止条件之2,同样宣告struct结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                } elseif ($char == '/') {
                    $lineString = $this->copyAnnotation($char, $lineString);
                } elseif ($char == "\n") {
                    break;
                } else {
                    $word .= $char;
                }
            } elseif ($this->state == 'lineEnd') {
                if ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                }
                break;
            } elseif ($this->state == 'end') {
                break;
            }
        }

        if (!$validLine) {
            return;
        }

        // 完成了这一行的词法解析,需要输出如下的字段
        //        echo "RAW tag:".$tag." requireType:".$requireType." type:".$type.
        //            " valueName:".$valueName. " wholeType:".$wholeType.
        //            " defaultValue:".$defaultValue." lineString:".$lineString."\n\n";

        if (!isset($tag) || empty($requireType) || empty($type) || empty($valueName)) {
            Utils::abnormalExit('error', '结构体' . $this->structName . '内格式错误,请更正tars');
        } elseif ($type == 'map' && empty($wholeType)) {
            Utils::abnormalExit('error', '结构体' . $this->structName . '内map格式错误,请更正tars');
        } elseif ($type == 'vector' && empty($wholeType)) {
            Utils::abnormalExit('error', '结构体' . $this->structName . '内vector格式错误,请更正tars');
        } else {
            $this->writeStructLine($tag, $requireType, $type, $valueName, $wholeType, $defaultValue);
        }
    }

    /**
     * @param $wholeType
     * 通过完整的类型获取vector的扩展类型
     * vector<CateObj> => new \TARS_VECTOR(new CateObj())
     * vector<string> => new \TARS_VECTOR(\TARS::STRING)
     * vector<map<string,CateObj>> => new \TARS_VECTOR(new \TARS_MAP(\TARS_MAP, ew CateObj()))
     */
    public function getExtType($wholeType, $valueName)
    {
        $state = 'init';
        $word = '';
        $extType = '';

        for ($i = 0; $i < strlen($wholeType); ++$i) {
            $char = $wholeType[$i];
            if ($state == 'init') {
                // 如果遇到了空格
                if (Utils::isSpace($char)) {
                    continue;
                }
                // 回车是停止符号
                elseif (Utils::inIdentifier($char)) {
                    $state = 'indentifier';
                    $word .= $char;
                } elseif (Utils::isReturn($char)) {
                    break;
                } elseif ($char == '>') {
                    $extType .= ')';
                    continue;
                }
            } elseif ($state == 'indentifier') {
                if ($char == '<') {
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= '(';
                    $word = '';
                    $state = 'init';
                } elseif ($char == '>') {
                    // 替换word,替换> 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ')';
                    $word = '';
                    $state = 'init';
                } elseif ($char == ',') {
                    // 替换word,替换, 恢复初始状态
                    // 替换word,替换< 恢复初始状态
                    $tmp = $this->VecMapReplace($word);
                    $extType .= $tmp;
                    $extType .= ',';
                    $word = '';
                    $state = 'init';
                } else {
                    $word .= $char;
                    continue;
                }
            }
        }

        return $extType;
    }

    public function VecMapReplace($word)
    {
        $word = trim($word);
        // 遍历所有的类型
        foreach (Utils::$wholeTypeMap as $key => $value) {
            $word = preg_replace('/\b' . $key . '\b/', $value, $word);
        }

        if (Utils::isStruct($word, $this->preStructs)) {
            $word = 'new ' . $word . '()';
        }

        return $word;
    }

    /**
     * @param $tag
     * @param $requireType
     * @param $type
     * @param $name
     * @param $wholeType
     * @param $defaultValue
     */
    public function writeStructLine($tag, $requireType, $type, $valueName, $wholeType, $defaultValue)
    {
        if ($requireType === 'require') {
            $requireFlag = 'true';
        } else {
            $requireFlag = 'false';
        }

        $this->consts .= Utils::indent(1) . 'const ' . strtoupper($valueName) . ' = ' . $tag . ';' . Utils::lineFeed(1);
        if (!empty($defaultValue)) {
            $this->variables .= Utils::indent(1) . 'public $' . $valueName . '=' . $defaultValue . ';' . ' ' . Utils::lineFeed(1);
        } else {
            $this->variables .= Utils::indent(1) . 'public $' . $valueName . ';' . ' ' . Utils::lineFeed(1);
        }

        // 基本类型,直接替换
        if (Utils::isBasicType($type)) {
            $this->fields .= Utils::indent(2) . 'self::' . strtoupper($valueName) . ' => [' . Utils::lineFeed(1) .
                Utils::indent(3) . "'name'=>'" . $valueName . "'," . Utils::lineFeed(1) .
                Utils::indent(3) . "'required'=>" . $requireFlag . ',' . Utils::lineFeed(1) .
                Utils::indent(3) . "'type'=>" . Utils::getRealType($type) . ',' . Utils::lineFeed(1) .
                Utils::indent(2) . '],' . Utils::lineFeed(1);
        } elseif (Utils::isStruct($type, $this->preStructs)) {
            $this->fields .= Utils::indent(2) . 'self::' . strtoupper($valueName) . ' => [' . Utils::lineFeed(1) .
                Utils::indent(3) . "'name'=>'" . $valueName . "'," . Utils::lineFeed(1) .
                Utils::indent(3) . "'required'=>" . $requireFlag . ',' . Utils::lineFeed(1) .
                Utils::indent(3) . "'type'=>" . Utils::getRealType($type) . ',' . Utils::lineFeed(1) .
                Utils::indent(2) . '],' . Utils::lineFeed(1);
            $this->extraContructs .= Utils::indent(2) . "\$this->$valueName = new $type();" . Utils::lineFeed(1);
        } elseif (Utils::isVector($type) || Utils::isMap($type)) {
            $extType = $this->getExtType($wholeType, $valueName);
            $this->extraExtInit .= Utils::indent(2) . '$this->' . $valueName . ' = ' . $extType . ';' . Utils::lineFeed(1);

            $this->fields .= Utils::indent(2) . 'self::' . strtoupper($valueName) . ' => [' . Utils::lineFeed(1) .
                Utils::indent(3) . "'name'=>'" . $valueName . "'," . Utils::lineFeed(1) .
                Utils::indent(3) . "'required'=>" . $requireFlag . ',' . Utils::lineFeed(1) .
                Utils::indent(3) . "'type'=>" . Utils::getRealType($type) . ',' . Utils::lineFeed(1) .
                Utils::indent(2) . '],' . Utils::lineFeed(1);
        } else {
            Utils::abnormalExit('error', '结构体struct' . $this->structName . '内类型有误,请更正tars');
        }
    }

    protected function getStructClassHeader()
    {
        return "<?php" . Utils::lineFeed(2) . "namespace " . $this->nsPrefix . '\\' .
            $this->config['structNs'] . ';' . Utils::lineFeed(2);
    }

    protected function getStructClassUniqueName($className)
    {
        return str_replace('\\', '_', $this->nsPrefix . '\\' . $this->config['structNs'] . '\\' . $className);
    }
}
