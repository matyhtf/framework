<?php

namespace SPF;

/**
 * 模型加载器
 * 产生一个模型的接口对象
 * @author Tianfeng.Han
 */
class ModelLoader
{
    protected $app = null;
    protected $_models = array();
    protected $_tables = array();

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    /**
     * 仅获取master
     * @param $model_name
     * @return mixed
     * @throws Error
     */
    public function __get($model_name)
    {
        return $this->loadModel($model_name, 'master');
    }

    /**
     * 多DB实例
     * @param $model_name
     * @param $params
     * @return mixed
     * @throws Error
     */
    public function __call($model_name, $params)
    {
        $db_key = count($params) < 1 ? 'master' : $params[0];
        return $this->loadModel($model_name, $db_key);
    }

    /**
     * 加载 Model
     * @param string $model_name
     * @param string $db_key
     * @return Model
     */
    public function loadModel(string $model_name, string $db_key = 'master'): Model
    {
        if (!isset($this->_models[$db_key][$model_name])) {
            $model_class = '\\App\\Model\\' . $model_name;
            $this->_models[$db_key][$model_name] = new $model_class($this->app, $db_key);
        }
        return $this->_models[$db_key][$model_name];
    }

    /**
     * 加载表
     * @param $table_name
     * @param $db_key
     * @return Model
     */
    public function loadTable($table_name, $db_key = 'master')
    {
        if (isset($this->_tables[$db_key][$table_name])) {
            return $this->_tables[$db_key][$table_name];
        } else {
            $model = new Model($this->app, $db_key);
            $model->table = $table_name;
            $this->_tables[$db_key][$table_name] = $model;
            return $model;
        }
    }
}
