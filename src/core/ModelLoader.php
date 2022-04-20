<?php

namespace SPF;
/**
 * 模型加载器
 * 产生一个模型的接口对象
 * @author Tianfeng.Han
 */
class ModelLoader
{
    protected $swoole = null;
    protected $_models = array();
    protected $_tables = array();

    function __construct($swoole)
    {
        $this->swoole = $swoole;
    }

    /**
     * 仅获取master
     * @param $model_name
     * @return mixed
     * @throws Error
     */
    function __get($model_name)
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
    function __call($model_name, $params)
    {
        $db_key = count($params) < 1 ? 'master' : $params[0];
        return $this->loadModel($model_name, $db_key);
    }

    /**
     * 加载Model
     * @param $model_name
     * @param $db_key
     * @return mixed
     * @throws Error
     */
    public function loadModel($model_name, $db_key = 'master')
    {
        if (isset($this->_models[$db_key][$model_name])) {
            return $this->_models[$db_key][$model_name];
        } else {
            $model_file = App::getInstance()->app_path . '/models/' . str_replace('\\', '/', $model_name) . '.php';
            $model_class = '\\App\\Model\\' . $model_name;
            if (!is_file($model_file)) {
                //严格的psr4格式 大驼峰 命名空间和文件夹对应
                $model_file = App::getInstance()->app_path . '/Models/' . str_replace('\\', '/', $model_name) . '.php';
                $model_class = '\\App\\Models\\' . $model_name;
            }
            if (!is_file($model_file)) {
                throw new Error("The model [<b>$model_name</b>] does not exist.");
            }

            require_once $model_file;
            $this->_models[$db_key][$model_name] = new $model_class($this->swoole, $db_key);
            return $this->_models[$db_key][$model_name];
        }
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
            $model = new Model($this->swoole, $db_key);
            $model->table = $table_name;
            $this->_tables[$db_key][$table_name] = $model;
            return $model;
        }
    }
}
