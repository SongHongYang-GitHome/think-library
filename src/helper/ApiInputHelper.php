<?php
/**
 * Desc: 快捷接收参数
 * Date: 2020-11-02
 */

namespace library\helper;
use think\App;
use think\Container;
use think\facade\Validate;
use think\Request;
use library\ApiController;

class ApiInputHelper
{
    /**
     * 当前应用容器
     * @var App
     */
    public $app;

    /**
     * 请求实例
     * @var Query
     */
    public $request;

    /**
     * 控制器实例
     * @var Query
     */
    public $controller;

    /**
     * 验证器规则
     * @var array
     */
    protected $rule;

    /**
     * 待验证的数据
     * @var array
     */
    protected $data;

    /**
     * 验证结果消息
     * @var array
     */
    protected $info;

    /**
     * 构造函数
     * InputHelper constructor.
     * @param App $app
     * @param ApiController $controller
     * @param Request $request
     */
    public function __construct(App $app, ApiController $controller, Request $request)
    {
        $this->app = $app;
        $this->controller = $controller;
        $this->request = $request;
    }

    /**
     * 输入验证器
     * @param array $data
     * @param array $rule
     * @param array $info
     * @return array
     */
    public function init($data, $rule, $info)
    {
        $this->data = $this->parse($data);
        list($this->rule, $this->info) = [$rule, $info];
        $validate = Validate::make($this->rule, $this->info);
        if ($validate->check($this->data)) {
            return $this->data;
        } else {
            $this->controller->failReturn($validate->getError());
        }
    }

    /**
     * 接收参数
     * @param array $inputs
     * ["title"] 直接指定
     * ["title", "zh"] 直接指定,设置默认值
     * ["title#name"] 别名,接口传入name
     * ["title#name", ""] 别名，接口传入name,设置默认值
     * @return array
     */
    protected function parse($inputs=[])
    {
        $data=[];
        foreach($inputs as $input) {
            if (is_string($input)) {
                if (strpos($input, '#') === false) {
                    $data[$input] = $this->request->param($input);
                } else {
                    list($name, $alias) = explode('#', $input);
                    $data[$name] = $this->request->param($alias);
                }
            } else {
                list($title, $default) = $input;
                if (strpos($title, '#') === false) {
                    $data[$title] = $this->request->param($title, $default);
                } else {
                    list($name, $alias) = explode('#', $title);
                    $data[$name] = $this->request->param($alias, $default);
                }
            }
        }
        return $data;
    }

    /**
     * 静态实例对象
     * @return object
     */
    public static function instance()
    {
        return Container::getInstance()->make(static::class);
    }
}
