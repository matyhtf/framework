<?php

namespace SPF\Rpc\Client;

class Request
{
    public $class;

    public $function;

    public $encodeBuffers = [];

    public $params = [];

    public $connection = [];

    public $buffer = null;

    /**
     * @var int
     */
    public $requestId = 0;

    /**
     * @var array
     */
    public $config = [];

    public function __construct($class, $function, array $params = [], array $connection = [], array $config = [])
    {
        $this->class = $class;
        $this->function = $function;
        $this->params = $params;
        $this->connection = $connection;
        $this->config = $config;

        $this->genRequestId();
    }

    public function getClass()
    {
        return $this->class;
    }

    /**
     * 获取去除公共命名空间前缀的类名
     * 
     * @return string
     */
    public function getSimpleClass()
    {
        // 对class去除公共命名空间前缀
        $class = $this->class;
        $localNsPrefix = $this->getConfig('localNsPrefix') . '\\';
        if (substr($class, 0, strlen($localNsPrefix)) == $localNsPrefix) {
            $class = substr($class, strlen($localNsPrefix));
        }

        return $class;
    }

    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @return string ns1.ns2.class@func
     */
    public function getCallFunction()
    {
        return str_replace('\\', '.', $this->getSimpleClass()) . '@' . $this->getFunction();
    }

    public function param($indexOrName)
    {

    }

    public function params()
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->connection['protocol'];
    }

    /**
     * @return array
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @param string $name
     * @param mixed $defautl
     * 
     * @return mixed
     */
    public function getConfig($name = null, $defautl = null)
    {
        if (is_null($name)) {
            return $this->config;
        }

        return $this->config[$name] ?? $defautl;
    }

    /**
     * 数据打包格式
     * 
     * @return int
     */
    public function getFormat()
    {
        return $this->getConfig('format', \SPF\Rpc\Formatter\FormatterFactory::FMT_TARS);
    }

    /**
     * @return int
     */
    public function requestId()
    {
        return $this->requestId;
    }

    /**
     * 生成RequestID
     */
    protected function genRequestId()
    {
        $this->requestId = base_convert(uniqid('', true), 16, 10);
    }
}
