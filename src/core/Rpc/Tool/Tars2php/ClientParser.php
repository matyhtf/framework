<?php

namespace SPF\Rpc\Tool\Tars2php;

class ClientParser
{
    public $namespaceName;
    public $moduleName;
    public $interfaceName;
    public $asInterfaceName;

    public $state;

    // 这个结构体,可能会引用的部分,包括其他的结构体、枚举类型、常量
    public $useStructs = [];
    public $extraUse;
    public $preStructs;
    public $preEnums;

    public $preNamespaceStructs;
    public $preNamespaceEnums;

    public $returnSymbol = "\n";
    public $doubleReturn = "\n\n";
    public $tabSymbol = "\t";
    public $doubleTab = "\t\t";
    public $tripleTab = "\t\t\t";
    public $quardupleTab = "\t\t\t\t";

    public $extraContructs = '';
    public $extraExtInit = '';

    public $consts = '';
    public $variables = '';
    public $fields = '';

    public $funcSet = '';

    public $servantName;

    public function __construct(
        $fp,
        $line,
        $namespaceName,
        $moduleName,
        $interfaceName,
        $preStructs,
        $preEnums,
        $servantName,
        $preNamespaceEnums,
        $preNamespaceStructs
    ) {
        $this->fp = $fp;
        $this->namespaceName = $namespaceName;
        $this->moduleName = $moduleName;
        $this->preStructs = $preStructs;
        $this->preEnums = $preEnums;
        $this->interfaceName = $interfaceName;
        $this->servantName = $servantName;

        $this->extraUse = '';
        $this->useStructs = [];

        $this->preNamespaceEnums = $preNamespaceEnums;
        $this->preNamespaceStructs = $preNamespaceStructs;
    }

    public function copyAnnotation()
    {
        // 再读入一个字符
        $nextChar = fgetc($this->fp);
        // 第一种
        if ($nextChar == '/') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar == "\n") {
                    $this->state = 'lineEnd';
                    break;
                }
            }

            return;
        } elseif ($nextChar == '*') {
            while (1) {
                $tmpChar = fgetc($this->fp);

                if ($tmpChar === false) {
                    Utils::abnormalExit('error', '注释换行错误,请检查' . $tmpChar);
                } elseif ($tmpChar === "\n") { } elseif (($tmpChar) === '*') {
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
            Utils::abnormalExit('error', '注释换行错误,请检查' . $nextChar);
        }
    }

    public function getFileHeader($prefix = '')
    {
        return "<?php\n\nnamespace " . $this->namespaceName . $prefix . ';' . $this->doubleReturn .
            'use Tars\\client\\CommunicatorConfig;' . $this->returnSymbol .
            'use Tars\\client\\Communicator;' . $this->returnSymbol .
            'use Tars\\client\\RequestPacket;' . $this->returnSymbol .
            'use Tars\\client\\TUPAPIWrapper;' . $this->returnSymbol .
            $this->returnSymbol;
    }

    public function getInterfaceBasic()
    {
        return $this->tabSymbol . 'protected $_communicator;' . $this->returnSymbol .
            $this->tabSymbol . 'protected $_iVersion;' . $this->returnSymbol .
            $this->tabSymbol . 'protected $_iTimeout;' . $this->returnSymbol .
            $this->tabSymbol . "public \$_servantName = \"$this->servantName\";" . $this->returnSymbol .
            $this->tabSymbol . "public \$_contexts = [];" . $this->returnSymbol .
            $this->tabSymbol . "public \$_statuses = [];" . $this->doubleReturn .
            $this->tabSymbol . 'public function __construct(CommunicatorConfig $config) {' . $this->returnSymbol .

            $this->doubleTab . 'try {' . $this->returnSymbol .
            $this->tripleTab . '$config->setServantName($this->_servantName);' . $this->returnSymbol .
            $this->tripleTab . '$this->_communicator = new Communicator($config);' . $this->returnSymbol .
            $this->tripleTab . '$this->_iVersion = $config->getIVersion();' . $this->returnSymbol .
            $this->tripleTab . '$this->_iTimeout = empty($config->getAsyncInvokeTimeout())?2:$config->getAsyncInvokeTimeout();' . $this->returnSymbol .
            $this->doubleTab . '} catch (\\Exception $e) {' . $this->returnSymbol .
            $this->tripleTab . 'throw $e;' . $this->returnSymbol .
            $this->doubleTab . '}' . $this->returnSymbol .
            $this->tabSymbol . '}' . $this->doubleReturn;
    }

    public function parse()
    {
        while ($this->state != 'end') {
            $this->state = 'init';
            $this->InterfaceFuncParseLine();
        }

        $interfaceClass = $this->getFileHeader('') . $this->extraUse . 'class ' . $this->interfaceName . ' {' . $this->returnSymbol;

        $interfaceClass .= $this->getInterfaceBasic();

        $interfaceClass .= $this->funcSet;

        $interfaceClass .= '}' . $this->doubleReturn;

        return [
            'syn' => $interfaceClass,
        ];
    }

    /**
     * @param $fp
     * @param $line
     * 这里必须要引入状态机了
     */
    public function InterfaceFuncParseLine()
    {
        $line = '';
        $this->state = 'init';
        while (1) {
            if ($this->state == 'init') {
                $char = fgetc($this->fp);

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
                $char = fgetc($this->fp);

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
                $char = fgetc($this->fp);
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
        if (!$line || $line[0] === '/' || $line[0] === '*' || $line === '{') {
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

        // 有必要先分成三个部分,返回类型、接口名、参数列表
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
                } elseif ($char == "\n") {
                    break;
                } elseif (Utils::inIdentifier($char)) {
                    $state = 'identifier';
                    $word .= $char;
                }
                // 终止条件之1,宣告interface结束
                elseif ($char == ')') {
                    break;
                } else {
                    Utils::abnormalExit('error', 'Interface:' . $this->interfaceName . '内格式错误,请更正tars');
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
                } elseif ($char == "\n") {
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
     * vector<CateObj> => new \TARS_VECTOR(new CateObj())
     * vector<string> => new \TARS_VECTOR(\TARS::STRING)
     * vector<map<string,CateObj>> => new \TARS_VECTOR(new \TARS_MAP(\TARS_MAP,new CateObj()))
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
            if (Utils::isStruct($word, $this->preStructs)) {
                if (!in_array($word, $this->useStructs)) {
                    $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                    $this->useStructs[] = $word;
                }

                $word = 'new ' . $word . '()';
            } elseif (in_array($word, $this->preNamespaceStructs)) {
                $words = explode('::', $word);
                $word = $words[1];
                if (!in_array($word, $this->useStructs)) {
                    $this->extraUse .= 'use protocol\\' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                    $this->useStructs[] = $word;
                }

                $word = 'new ' . $word . '()';
                break;
            } else {
                $word = preg_replace('/\b' . $key . '\b/', $value, $word);
            }
        }

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
                    } elseif ($char == "\n") {
                        break;
                    } elseif (Utils::inIdentifier($char)) {
                        $state = 'identifier';
                        $word .= $char;
                    } else {
                        Utils::abnormalExit('error', 'Interface:' . $this->interfaceName . '内格式错误,请更正tars');
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
                        } elseif (Utils::isStruct($word, $this->preStructs)) {

                            // 同时要把它增加到本Interface的依赖中
                            if (!in_array($word, $this->useStructs)) {
                                $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                                $this->useStructs[] = $word;
                            }

                            $type = $word;
                            $state = 'init';
                            $word = '';
                        } elseif (Utils::isEnum($word, $this->preEnums)) {
                            $type = 'unsigned byte';
                            $state = 'init';
                            $word = '';
                        } elseif (in_array($word, $this->preNamespaceStructs)) {
                            $word = explode('::', $word);
                            $word = $word[1];
                            // 同时要把它增加到本Interface的依赖中
                            if (!in_array($word, $this->useStructs)) {
                                $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                                $this->useStructs[] = $word;
                            }

                            $type = $word;
                            $state = 'init';
                            $word = '';
                        } elseif (in_array($word, $this->preNamespaceEnums)) {
                            $type = 'unsigned byte';
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
        if (Utils::isStruct($returnType, $this->preStructs)) {
            if (!in_array($returnType, $this->useStructs)) {
                $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $returnType . ';' . $this->returnSymbol;
                $this->useStructs[] = $returnType;
            }
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
                //echo "[debug][state={$this->state}]word:".$word."\n";
                if (Utils::isSpace($char)) {
                    if (Utils::isBasicType($word)) {
                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif (Utils::isStruct($word, $this->preStructs)) {

                        // 同时要把它增加到本Interface的依赖中
                        if (!in_array($word, $this->useStructs)) {
                            $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                            $this->useStructs[] = $word;
                        }

                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif (Utils::isEnum($word, $this->preEnums)) {
                        $type = 'unsigned byte';
                        $state = 'init';
                        $word = '';
                    } elseif (in_array($word, $this->preNamespaceStructs)) {
                        $word = explode('::', $word);
                        $word = $word[1];
                        // 同时要把它增加到本Interface的依赖中
                        if (!in_array($word, $this->useStructs)) {
                            $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $word . ';' . $this->returnSymbol;
                            $this->useStructs[] = $word;
                        }

                        $type = $word;
                        $state = 'init';
                        $word = '';
                    } elseif (in_array($word, $this->preNamespaceEnums)) {
                        $type = 'unsigned byte';
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

        // 处理通用的头部
        $funcHeader = $this->generateFuncHeader($funcName, $inParams, $outParams);
        $returnInfo = $this->returnParser($returnType);

        $funcBodyArr = $this->generateFuncBody($inParams, $outParams, $returnInfo);
        $synFuncBody = $funcBodyArr['syn'];

        $funcTail = $this->tabSymbol . '}' . $this->doubleReturn;

        $this->funcSet .= $funcHeader . $synFuncBody . $funcTail;
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
            $paramPrefix = Utils::paramTypeMap($param['type']);
            $paramSuffix = '$' . $param['valueName'];
            $paramsStr .= !empty($paramPrefix) ? $paramPrefix . ' ' . $paramSuffix . ',' : $paramSuffix . ',';
        }

        foreach ($outParams as $param) {
            $paramPrefix = Utils::paramTypeMap($param['type']);
            $paramSuffix = '&$' . $param['valueName'];
            $paramsStr .= !empty($paramPrefix) ? $paramPrefix . ' ' . $paramSuffix . ',' : $paramSuffix . ',';
        }

        $paramsStr = trim($paramsStr, ',');
        $paramsStr .= ') {' . $this->returnSymbol;

        $funcHeader = $this->tabSymbol . 'public function ' . $funcName . '(' . $paramsStr;

        return $funcHeader;
    }

    /**
     * @param $funcName
     * @param $inParams
     * @param $outParams
     * 生成函数的包体
     */
    public function generateFuncBody($inParams, $outParams, $returnInfo)
    {
        $bodyPrefix = $this->doubleTab . 'try {' . $this->returnSymbol;

        $bodySuffix = $this->doubleTab . 'catch (\\Exception $e) {' . $this->returnSymbol .
            $this->tripleTab . 'throw $e;' . $this->returnSymbol .
            $this->doubleTab . '}' . $this->returnSymbol;

        $bodyMiddle = $this->tripleTab . '$requestPacket = new RequestPacket();' . $this->returnSymbol .
            $this->tripleTab . '$requestPacket->_iVersion = $this->_iVersion;' . $this->returnSymbol .
            $this->tripleTab . '$requestPacket->_funcName = __FUNCTION__;' . $this->returnSymbol .
            $this->tripleTab . '$requestPacket->_servantName = $this->_servantName;' . $this->returnSymbol .
            $this->tripleTab . '$requestPacket->_contexts = $this->_contexts;' . $this->returnSymbol .
            $this->tripleTab . '$requestPacket->_statuses = $this->_statuses;' . $this->returnSymbol .
            $this->tripleTab . '$encodeBufs = [];' . $this->doubleReturn;

        $commonPrefix = '$__buffer = TUPAPIWrapper::';

        $index = 0;
        foreach ($inParams as $param) {
            ++$index;
            $type = $param['type'];

            $packMethod = Utils::getPackMethods($type);
            $valueName = $param['valueName'];

            // 判断如果是vector需要特别的处理
            if (Utils::isVector($type)) {
                $vecFill = $this->tripleTab . '$' . $valueName . '_vec = ' . $this->getExtType($param['wholeType'], $valueName) . ';' . $this->returnSymbol .
                    $this->tripleTab . 'foreach($' . $valueName . ' as ' . '$single' . $valueName . ') {' . $this->returnSymbol .
                    $this->quardupleTab . '$' . $valueName . '_vec->pushBack($single' . $valueName . ');' . $this->returnSymbol .
                    $this->tripleTab . '}' . $this->returnSymbol;
                $bodyMiddle .= $vecFill;
                $bodyMiddle .= $this->tripleTab . $commonPrefix . $packMethod . '("' . $valueName . "\",{$index},\$" . $valueName . '_vec,$this->_iVersion);' . $this->returnSymbol;
            }

            // 判断如果是map需要特别的处理
            elseif (Utils::isMap($type)) {
                $mapFill = $this->tripleTab . '$' . $valueName . '_map = ' . $this->getExtType($param['wholeType'], $valueName) . ';' . $this->returnSymbol .
                    $this->tripleTab . 'foreach($' . $valueName . ' as ' . '$key => $value) {' . $this->returnSymbol .
                    $this->quardupleTab . '$' . $valueName . '_map->pushBack([$key => $value]);' . $this->returnSymbol .
                    $this->tripleTab . '}' . $this->returnSymbol;
                $bodyMiddle .= $mapFill;
                $bodyMiddle .= $this->tripleTab . $commonPrefix . $packMethod . '("' . $valueName . "\",{$index},\$" . $valueName . '_map,$this->_iVersion);' . $this->returnSymbol;
            }
            // 针对struct,需要额外的use过程
            elseif (Utils::isStruct($type, $this->preStructs)) {
                if (!in_array($type, $this->useStructs)) {
                    $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $param['type'] . ';' . $this->returnSymbol;
                    $this->useStructs[] = $param['type'];
                }
                $bodyMiddle .= $this->tripleTab . $commonPrefix . $packMethod . '("' . $valueName . "\",{$index},\$" . $valueName . ',$this->_iVersion);' . $this->returnSymbol;
            } else {
                $bodyMiddle .= $this->tripleTab . $commonPrefix . $packMethod . '("' . $valueName . "\",{$index},\$" . $valueName . ',$this->_iVersion);' . $this->returnSymbol;
            }

            $bodyMiddle .= $this->tripleTab . "\$encodeBufs['{$valueName}'] = \$__buffer;" . $this->returnSymbol;
        }

        $bodyMiddle .= $this->tripleTab . '$requestPacket->_encodeBufs = $encodeBufs;' .
            $this->doubleReturn;

        $bodyMiddle .= $this->tripleTab . '$sBuffer = $this->_communicator->invoke($requestPacket,$this->_iTimeout);' . $this->doubleReturn;

        foreach ($outParams as $param) {
            ++$index;

            $type = $param['type'];

            $unpackMethods = Utils::getUnpackMethods($type);
            $name = $param['valueName'];

            if (Utils::isBasicType($type)) {
                $bodyMiddle .= $this->tripleTab . "\$$name = TUPAPIWrapper::" . $unpackMethods . '("' . $name . "\",{$index},\$sBuffer,\$this->_iVersion);" . $this->returnSymbol;
            } else {
                // 判断如果是vector需要特别的处理
                if (Utils::isVector($type) || Utils::isMap($type)) {
                    $bodyMiddle .= $this->tripleTab . "\$$name = TUPAPIWrapper::" . $unpackMethods . '("' . $name . "\",{$index}," . $this->getExtType($param['wholeType'], $name) . ',$sBuffer,$this->_iVersion);' . $this->returnSymbol;
                }
                // 如果是struct
                elseif (Utils::isStruct($type, $this->preStructs)) {
                    $bodyMiddle .= $this->tripleTab . '$ret = TUPAPIWrapper::' . $unpackMethods . '("' . $name . "\",{$index},\$$name,\$sBuffer,\$this->_iVersion);" . $this->returnSymbol;

                    if (!in_array($type, $this->useStructs)) {
                        $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $param['type'] . ';' . $this->returnSymbol;
                        $this->useStructs[] = $param['type'];
                    }
                }
            }
        }

        // 还要尝试去获取一下接口的返回码哦
        $returnUnpack = Utils::getUnpackMethods($returnInfo['type']);
        $valueName = $returnInfo['valueName'];

        if ($returnInfo['type'] !== 'void') {
            if (Utils::isVector($returnInfo['type']) || Utils::isMap($returnInfo['type'])) {
                $bodyMiddle .= $this->tripleTab . 'return TUPAPIWrapper::' . $returnUnpack . '("",0,'
                    . $this->getExtType($returnInfo['wholeType'], $valueName) . ',$sBuffer,$this->_iVersion);' . $this->doubleReturn .
                    $this->doubleTab . '}' . $this->returnSymbol;
            } elseif (Utils::isStruct($returnInfo['type'], $this->preStructs)) {
                $bodyMiddle .= $this->tripleTab . "\$returnVal = new $valueName();" . $this->returnSymbol;
                $bodyMiddle .= $this->tripleTab . 'TUPAPIWrapper::' . $returnUnpack . '("",0,$returnVal,$sBuffer,$this->_iVersion);' . $this->returnSymbol;
                $bodyMiddle .= $this->tripleTab . 'return $returnVal;' . $this->doubleReturn .
                    $this->doubleTab . '}' . $this->returnSymbol;

                if (!in_array($returnInfo['type'], $this->useStructs)) {
                    $this->extraUse .= 'use ' . $this->namespaceName . '\\classes\\' . $returnInfo['type'] . ';' . $this->returnSymbol;
                    $this->useStructs[] = $returnInfo['type'];
                }
            } else {
                $bodyMiddle .= $this->tripleTab . 'return TUPAPIWrapper::' . $returnUnpack . '("",0,$sBuffer,$this->_iVersion);' . $this->doubleReturn .
                    $this->doubleTab . '}' . $this->returnSymbol;
            }
        } else {
            $bodyMiddle .= $this->doubleTab . '}' . $this->returnSymbol;
        }

        $bodyStr = $bodyPrefix . $bodyMiddle . $bodySuffix;

        return [
            'syn' => $bodyStr,
        ];
    }
}
