<?php

namespace SPF\Rpc\Tool\Tars2php;

class ServantParser
{
    public $namespaceName;
    public $interfaceName;

    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $useStructs = [];
    public $extraUse;
    public $preStructs;
    public $preEnums;

    public $preNamespaceEnums = [];
    public $preNamespaceStructs = [];

    public $extraContructs = '';
    public $extraExtType = '';
    public $extraExtInit = '';

    public $consts = '';
    public $variables = '';
    public $fields = '';

    public $funcSet = '';

    protected $nsPrefix;
    protected $config;
    protected $structNsPrefix;

    public function __construct(
        $fp,
        $interfaceName,
        $nsPrefix,
        $config,
        $preStructs,
        $preEnums,
        $preNamespaceEnums,
        $preNamespaceStructs
    ) {
        $this->fp = $fp;
        $this->interfaceName = $interfaceName;
        $this->nsPrefix = $nsPrefix;
        $this->config = $config;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;

        $this->extraUse = '';
        $this->useStructs = [];

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;

        $this->structNsPrefix = $this->nsPrefix . '\\' . $this->config['structNs'] . '\\';
    }

    public function isEnum($word)
    {
        return in_array($word, $this->preEnums);
    }

    public function isStruct($word)
    {
        return in_array($word, $this->preStructs);
    }

    public function getFileHeader($prefix = '')
    {
        return "<?php" . Utils::lineFeed(2) . "namespace " . $this->nsPrefix . $prefix . ';' .
            Utils::lineFeed(2);
    }

    public function parse()
    {
        while ($this->state != 'end') {
            $this->InterfaceFuncParseLine();
        }

        // todo serverName+servant
        $interfaceClass = $this->getFileHeader('\\' . $this->config['interfaceNs'])
            . $this->extraUse . ($this->extraUse ? Utils::lineFeed(1) : '')
            . 'interface ' . $this->interfaceName . Utils::lineFeed(1) . '{' . Utils::lineFeed(1);

        $interfaceClass .= $this->funcSet;

        $interfaceClass .= '}' . Utils::lineFeed(1);

        return $interfaceClass;
    }

    /**
     * @param $startChar
     * @param $lineString
     *
     * @return string
     *                专门处理注释
     */
    public function copyAnnotation()
    {
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if ($nextChar == '/') {
            while (1) {
                $tmpChar = fgetc($this->fp);
                if (Utils::isReturn($tmpChar)) {
                    $this->state = 'lineEnd';
                    break;
                }
            }

            return;
        } elseif ($nextChar == '*') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar === false) {
                    Utils::abnormalExit('error', $this->interfaceName . '注释换行错误,请检查');
                } elseif (Utils::isReturn($tmpChar)) { } elseif (($tmpChar) === '*') {
                    $nextnextChar = fgetc($this->fp);
                    if ($nextnextChar == '/') {
                        return;
                    } else {
                        $pos = ftell($this->fp);
                        fseek($this->fp, $pos - 1);
                    }
                }
            }
        }
        // 注释不正常
        else {
            Utils::abnormalExit('error', $this->interfaceName . '注释换行错误,请检查');
        }
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     * 这里并不一定要一个line呀,应该找)作为结束符
     */
    public function InterfaceFuncParseLine()
    {
        $line = '';
        $this->state = 'init';
        while (1) {
            $char = fgetc($this->fp);

            if ($this->state == 'init') {
                // 有可能是换行
                if ($char == '{' || Utils::isReturn($char)) {
                    continue;
                }
                // 遇到了注释会用贪婪算法全部处理完,同时填充到struct的类里面去
                elseif ($char == '/') {
                    $this->copyAnnotation();
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $this->state = 'identifier';
                    $line .= $char;
                }
                // 终止条件之1,宣告struct结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                    break;
                }
            } elseif ($this->state == 'identifier') {
                if ($char == '/') {
                    $this->copyAnnotation();
                } elseif ($char == ';') {
                    $line .= $char;
                    break;
                }
                // 终止条件之2,同样宣告interface结束
                elseif ($char == '}') {
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'end';
                } elseif (Utils::isReturn($char)) {
                    continue;
                } elseif ($char == ')') {
                    $line .= $char;
                    // 需要贪心的读到"\n"为止
                    while (($lastChar = fgetc($this->fp)) != "\n") {
                        continue;
                    }
                    $this->state = 'lineEnd';
                } else {
                    $line .= $char;
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

        if (empty($line)) {
            return;
        }

        $line = trim($line);

        // 如果空行，或者是注释，或者是大括号就直接略过
        if (!trim($line) || trim($line)[0] === '/' || trim($line)[0] === '*' || trim($line) === '{') {
            return;
        }

        $endFlag = strpos($line, '};');
        if ($endFlag !== false) {
            $this->state = 'end';

            return;
        }

        $endFlag = strpos($line, '}');
        if ($endFlag !== false) {
            $this->state = 'end';

            return;
        }

        // 有必要先分成三个部分,返回类型、接口名、参数列表 todo
        $tokens = preg_split('/\(/', $line, 2);
        $mix = trim($tokens[0]);
        $rest = $tokens[1];

        $pices = preg_split('/\s+/', $mix);

        $funcName = $pices[count($pices) - 1];

        $returnType = implode('', array_slice($pices, 0, count($pices) - 1));

        $state = 'init';
        $word = '';

        $params = [];

        for ($i = 0; $i < strlen($rest); ++$i) {
            $char = $rest[$i];

            if ($state == 'init') {
                // 有可能是换行
                if ($char == '(' || Utils::isSpace($char)) {
                    continue;
                } elseif (Utils::isReturn($char)) {
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $state = 'identifier';
                    $word .= $char;
                }
                // 终止条件之1,宣告interface结束
                elseif ($char == ')') {
                    break;
                } else {
                    Utils::abnormalExit('error', 'Interface' . $this->interfaceName . '内格式错误,请更正tars in line:' . __LINE__);
                }
            } elseif ($state == 'identifier') {
                if ($char == ',') {
                    $params[] = $word;
                    $state = 'init';
                    $word = '';
                    continue;
                }
                // 标志着map和vector的开始,不等到'>'的结束不罢休
                // 这时候需要使用栈来push,然后一个个对应的pop,从而达到type的遍历
                elseif ($char == '<') {
                    $mapVectorStack = [];
                    $word .= $char;
                    array_push($mapVectorStack, '<');
                    while (!empty($mapVectorStack)) {
                        $moreChar = $rest[$i + 1];
                        $word .= $moreChar;
                        if ($moreChar == '<') {
                            array_push($mapVectorStack, '<');
                        } elseif ($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                        ++$i;
                    }
                    continue;
                } elseif ($char == ')') {
                    $params[] = $word;
                    break;
                } elseif ($char == ';') {
                    continue;
                }
                // 终止条件之2,同样宣告struct结束
                elseif ($char == '}') {
                    $state = 'end';
                } elseif (Utils::isReturn($char)) {
                    break;
                } else {
                    $word .= $char;
                }
            } elseif ($state == 'lineEnd') {
                break;
            } elseif ($state == 'end') {
                break;
            }
        }

        $this->writeInterfaceLine($returnType, $funcName, $params);
    }

    /**
     * @param $wholeType
     * 通过完整的类型获取vector的扩展类型
     */
    public function getExtType($wholeType)
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
            if ($this->isStruct($word)) {
                if (!in_array($word, $this->useStructs)) {
                    $this->useStructs[] = $word;
                }
                $word = '\\' . $this->structNsPrefix . $word;
                break;
            } elseif (in_array($word, $this->preNamespaceStructs)) {
                $words = explode('::', $word);
                $word = $words[1];
                if (!in_array($word, $this->useStructs)) {
                    $this->useStructs[] = $word;
                }
                $word = '\\' . $this->structNsPrefix . $word;
                break;
            } else {
                $word = preg_replace('/\b' . $key . '\b/', $value, $word);
            }
        }

        $word = trim($word, 'new ');

        return $word;
    }

    public function paramParser($params)
    {

        // 输入和输出的参数全部捋一遍
        $inParams = [];
        $outParams = [];
        foreach ($params as $param) {
            $state = 'init';
            $word = '';
            $wholeType = '';
            $paramType = 'in';
            $type = '';
            $mapVectorState = false;

            for ($i = 0; $i < strlen($param); ++$i) {
                $char = $param[$i];
                if ($state == 'init') {
                    // 有可能是换行
                    if (Utils::isSpace($char)) {
                        continue;
                    } elseif (Utils::isReturn($char)) {
                        break;
                    } elseif (Utils::inIdentifier($char)) {
                        $state = 'identifier';
                        $word .= $char;
                    } else {
                        Utils::abnormalExit('error', 'Interface内格式错误,请更正tars in line:' . __LINE__);
                    }
                } elseif ($state == 'identifier') {
                    // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                    // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                    if (Utils::isSpace($char)) {
                        if ($word == 'out') {
                            $paramType = $word;
                            $state = 'init';
                            $word = '';
                        } elseif (Utils::isBasicType($word)) {
                            $type = $word;
                            $state = 'init';
                            $word = '';
                        } elseif ($this->isStruct($word)) {

                            // 同时要把它增加到本Interface的依赖中
                            if (!in_array($word, $this->useStructs)) {
                                $this->extraUse .= 'use ' . $this->structNsPrefix . $word . ';' . Utils::lineFeed(1);
                                $this->useStructs[] = $word;
                            }

                            $type = $word;
                            $state = 'init';
                            $word = '';
                        } elseif ($this->isEnum($word)) {
                            $type = 'int';
                            $state = 'init';
                            $word = '';
                        } elseif (in_array($word, $this->preNamespaceStructs)) {
                            $word = explode('::', $word);
                            $word = $word[1];
                            // 同时要把它增加到本Interface的依赖中
                            if (!in_array($word, $this->useStructs)) {
                                $this->extraUse .= 'use ' . $this->structNsPrefix . $word . ';' . Utils::lineFeed(1);
                                $this->useStructs[] = $word;
                            }

                            $type = $word;
                            $state = 'init';
                            $word = '';
                        } elseif (in_array($word, $this->preNamespaceEnums)) {
                            $type = 'int';
                            $state = 'init';
                            $word = '';
                        } elseif (Utils::isMap($word)) {
                            $mapVectorState = true;
                        } elseif (Utils::isVector($word)) {
                            $mapVectorState = true;
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
                                $state = 'init';
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
                            $moreChar = $param[$i + 1];
                            $wholeType .= $moreChar;
                            if ($moreChar == '<') {
                                array_push($mapVectorStack, '<');
                            } elseif ($moreChar == '>') {
                                array_pop($mapVectorStack);
                            }
                            ++$i;
                        }

                        $state = 'init';
                        $word = '';
                    } else {
                        $word .= $char;
                    }
                }
            }

            if (!empty($word)) {
                $valueName = $word;
            }

            if ($paramType == 'in') {
                $inParams[] = [
                    'type' => $type,
                    'wholeType' => $wholeType,
                    'valueName' => $valueName,
                ];
            } else {
                $outParams[] = [
                    'type' => $type,
                    'wholeType' => $wholeType,
                    'valueName' => $valueName,
                ];
            }
        }

        return [
            'in' => $inParams,
            'out' => $outParams,
        ];
    }

    public function returnParser($returnType)
    {
        if ($this->isStruct($returnType)) {
            if (!in_array($returnType, $this->useStructs)) {
                $this->useStructs[] = $returnType;
            }
            $returnInfo = [
                'type' => $returnType,
                'wholeType' => $returnType,
                'valueName' => $returnType,
            ];

            return $returnInfo;
        } elseif ($this->isEnum($returnType)) {
            $returnInfo = [
                'type' => $returnType,
                'wholeType' => $returnType,
                'valueName' => $returnType,
            ];

            return $returnInfo;
        } elseif (Utils::isBasicType($returnType)) {
            $returnInfo = [
                'type' => $returnType,
                'wholeType' => $returnType,
                'valueName' => $returnType,
            ];

            return $returnInfo;
        }

        $state = 'init';
        $word = '';
        $wholeType = '';
        $type = '';
        $mapVectorState = false;
        $valueName = '';

        for ($i = 0; $i < strlen($returnType); ++$i) {
            $char = $returnType[$i];
            if ($state == 'init') {
                // 有可能是换行
                if (Utils::isSpace($char)) {
                    continue;
                } elseif ($char == "\n") {
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $state = 'identifier';
                    $word .= $char;
                } else {
                    Utils::abnormalExit('error', 'Interface内格式错误,请更正tars');
                }
            } elseif ($state == 'identifier') {
                // 如果遇到了space,需要检查是不是在map或vector的类型中,如果当前积累的word并不合法
                // 并且又不是处在vector或map的前置状态下的话,那么就是出错了
                if (Utils::isSpace($char)) {
                    if (Utils::isBasicType($word)) {
                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif ($this->isStruct($word)) {

                        // 同时要把它增加到本Interface的依赖中
                        if (!in_array($word, $this->useStructs)) {
                            $this->extraUse .= 'use ' . $this->structNsPrefix . $word . ';' . Utils::lineFeed(1);
                            $this->useStructs[] = $word;
                        }

                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif ($this->isEnum($word)) {
                        $type = 'int';
                        $state = 'init';
                        $word = '';
                    } elseif (in_array($word, $this->preNamespaceStructs)) {
                        $word = explode('::', $word);
                        $word = $word[1];
                        // 同时要把它增加到本Interface的依赖中
                        if (!in_array($word, $this->useStructs)) {
                            $this->extraUse .= 'use ' . $this->structNsPrefix . $word . ';' . Utils::lineFeed(1);
                            $this->useStructs[] = $word;
                        }

                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif (in_array($word, $this->preNamespaceEnums)) {
                        $type = 'int';
                        $state = 'init';
                        $word = '';
                    } elseif (Utils::isMap($word)) {
                        $mapVectorState = true;
                    } elseif (Utils::isVector($word)) {
                        $mapVectorState = true;
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
                            $state = 'init';
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
                        $moreChar = $returnType[$i + 1];
                        $wholeType .= $moreChar;
                        if ($moreChar == '<') {
                            array_push($mapVectorStack, '<');
                        } elseif ($moreChar == '>') {
                            array_pop($mapVectorStack);
                        }
                        ++$i;
                    }

                    $state = 'init';
                    $word = '';
                } else {
                    $word .= $char;
                }
            }
        }

        $returnInfo = [
            'type' => $type,
            'wholeType' => $wholeType,
            'valueName' => $valueName,
        ];

        return $returnInfo;
    }

    /**
     * @param $tag
     * @param $requireType
     * @param $type
     * @param $name
     * @param $wholeType
     * @param $defaultValue
     */
    public function writeInterfaceLine($returnType, $funcName, $params)
    {
        $result = $this->paramParser($params);
        $inParams = $result['in'];
        $outParams = $result['out'];

        $returnInfo = $this->returnParser($returnType);

        $funcAnnotation = $this->generateFuncAnnotation($inParams, $outParams, $returnInfo);

        // 函数定义恰恰是要放在最后面了
        $funcDefinition = $this->generateFuncHeader($funcName, $inParams, $outParams);

        $this->funcSet .= Utils::lineFeed(1) . $funcAnnotation . $funcDefinition;
    }

    private function paramTypeMap($paramType)
    {
        if (Utils::isBasicType($paramType) || Utils::isMap($paramType) || Utils::isVector($paramType)) {
            return '';
        } else {
            return $paramType;
        }
    }
    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     *
     * @return string
     */
    public function generateFuncHeader($funcName, $inParams, $outParams)
    {
        $paramsStr = '';
        foreach ($inParams as $param) {
            $paramPrefix = $this->paramTypeMap($param['type']);
            $paramSuffix = '$' . $param['valueName'];
            $paramsStr .= !empty($paramPrefix) ? $paramPrefix . ' ' . $paramSuffix . ', ' : $paramSuffix . ', ';
        }

        foreach ($outParams as $param) {
            $paramPrefix = $this->paramTypeMap($param['type']);
            $paramSuffix = '&$' . $param['valueName'];
            $paramsStr .= !empty($paramPrefix) ? $paramPrefix . ' ' . $paramSuffix . ', ' : $paramSuffix . ', ';
        }

        $paramsStr = trim($paramsStr, ', ');
        $paramsStr .= ');' . Utils::lineFeed(1);

        $funcHeader = Utils::indent(1) . 'public function ' . $funcName . '(' . $paramsStr;

        return $funcHeader;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * 生成函数的包体
     */
    public function generateFuncAnnotation($inParams, $outParams, $returnInfo)
    {
        $bodyPrefix = Utils::indent(1) . '/**' . Utils::lineFeed(1);

        $bodyMiddle = '';

        foreach ($inParams as $param) {
            $annotation = Utils::indent(1) . ' * @param ';
            $type = $param['type'];
            $valueName = $param['valueName'];
            $wholeType = $param['wholeType'];

            // 判断如果是vector需要特别的处理
            if (Utils::isVector($type)) {
                // 结构在前，tars结构在后，方便IDE识别
                // $annotation .= 'vector' . ' $' . $valueName . ' ' . $this->getExtType($wholeType);
                $annotation .= $this->getExtType($wholeType) . ' $' . $valueName . ' #vector';
            }

            // 判断如果是map需要特别的处理
            elseif (Utils::isMap($type)) {
                $annotation .= $this->getExtType($wholeType) . ' $' . $valueName . ' #map';
            }
            // 针对struct,需要额外的use过程
            elseif ($this->isStruct($type)) {
                $annotation .= '\\' . $this->structNsPrefix . $type . ' $' . $valueName . ' #struct';
            } else {
                $annotation .= $type . ' $' . $valueName;
            }
            $bodyMiddle .= $annotation . Utils::lineFeed(1);
        }

        foreach ($outParams as $param) {
            $annotation = Utils::indent(1) . ' * @param ';
            $type = $param['type'];
            $valueName = $param['valueName'];
            $wholeType = $param['wholeType'];

            if (Utils::isBasicType($type)) {
                $annotation .= $type . ' $' . $valueName . ' #&';
            } else {
                // 判断如果是vector需要特别的处理
                if (Utils::isVector($type)) {
                    // $annotation .= 'vector' . ' $' . $valueName . ' ' . $this->getExtType($wholeType);
                    $annotation .= $this->getExtType($wholeType) . ' $' . $valueName . ' #&vector';
                } elseif (Utils::isMap($type)) {
                    // $annotation .= 'map' . ' $' . $valueName . ' ' . $this->getExtType($wholeType);
                    $annotation .= $this->getExtType($wholeType) . ' $' . $valueName . ' #&map';
                }
                // 如果是struct
                elseif ($this->isStruct($type)) {
                    // $annotation .= 'struct' . ' $' . $valueName . ' \\' . $this->structNsPrefix . $type;
                    $annotation .= '\\' . $this->structNsPrefix . $type . ' $' . $valueName . ' #&struct';
                }
            }

            // $annotation .= ' =out=' . Utils::lineFeed(1);
            $annotation .= Utils::lineFeed(1);
            $bodyMiddle .= $annotation;
        }

        // 还要尝试去获取一下接口的返回码哦
        $type = $returnInfo['type'];
        $valueName = $returnInfo['valueName'];
        $wholeType = $returnInfo['wholeType'];

        $annotation = Utils::indent(1) . ' *' . Utils::lineFeed(1) . Utils::indent(1) . ' * @return ';

        if ($type !== 'void') {
            if (Utils::isVector($type)) {
                // 结构在前，tars结构在后，方便IDE识别
                // $annotation .= 'vector ' . $this->getExtType($wholeType);
                $annotation .= $this->getExtType($wholeType) . ' #vector';
            } elseif (Utils::isMap($type)) {
                // 结构在前，tars结构在后，方便IDE识别
                // $annotation .= 'map ' . $this->getExtType($wholeType);
                $annotation .= $this->getExtType($wholeType) . ' #map';
            } elseif ($this->isStruct($type)) {
                // 结构在前，tars结构在后，方便IDE识别
                // $annotation .= 'struct \\' . $this->structNsPrefix . $type;
                $annotation .= '\\' . $this->structNsPrefix . $type . ' #struct';
            } else {
                $annotation .= $type;
            }
        } else {
            $annotation .= 'void';
        }

        $bodyMiddle .= $annotation . Utils::lineFeed(1) . Utils::indent(1) . ' */' . Utils::lineFeed(1);

        $bodyStr = $bodyPrefix . $bodyMiddle;

        return  $bodyStr;
    }
}
