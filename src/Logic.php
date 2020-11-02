<?php
/**
 * Desc: 逻辑层基类
 * Date: 2020-11-02
 */

namespace library;

use think\App;
use think\Container;
use think\Exception;
use think\Request;

class Logic
{
    /**
     * 当前实例应用
     * @var App
     */
    protected $app;

    /**
     * 当前请求对象
     * @var Request
     */
    protected $request;

    /**
     * 定义当前Service需要使用依赖
     */
    public $autowired = [];

    /**
     * 已注入的依赖数组
     */
    protected $require = [];

    /**
     * Service constructor.
     * @param App $app
     * @param Request $request
     */
    public function __construct(App $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;
        $this->initialize();
    }

    /**
     * 静态实例对象
     * @return object
     */
    public static function instance()
    {
        return Container::getInstance()->make(static::class);
    }

    /**
     * 初始化服务
     * @return $this
     */
    protected function initialize()
    {
        $this->autowired();
        return $this;
    }

    /**
     * 自动装配模型
     */
    public function autowired()
    {
        foreach ($this->autowired as $name=>$path) $this->bind($name, $path);
    }

    /**
     * 绑定一个类到容器中
     * @param $name
     * @param $path
     */
    protected function bind($name, $path)
    {
        bind($path);
        $this->require[$name] = app($path);
    }

    /**
     * 指定获取已注入的类
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function _load($name)
    {
        if (!isset($this->require[$name])) throw new Exception(sprintf("类%s没有注入", $name), 10001);
        return $this->require[$name];
    }

    /**
     * 魔术方法,指定获取已注入的模型类
     * 例如：$this->member()
     * @param $key
     * @param $args
     * @return mixed
     * @throws Exception
     */
    public function __call($key, $args)
    {
        return $this->_load($key);
    }

    /**
     * 魔术方法,指定获取已注入的模型类
     * 例如：$this->member
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        return $this->_load($name);
    }
}
