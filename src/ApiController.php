<?php
/**
 * Desc: 接口控制器基类
 * Date: 2020-11-02
 */

namespace library;

use library\helper\ApiInputHelper;
use library\helper\ApiPageHelper;
use library\helper\ApiQueryHelper;
use think\Container;
use think\Exception;
use think\exception\HttpResponseException;
use think\facade\Response;

class ApiController extends \think\Controller
{
    /**
     * 定义当前Service需要使用依赖
     */
    public $autowired = [];

    /**
     * 已注入的依赖数组
     */
    protected $require = [];

    /**
     * 数据库实例
     * @var Query
     */
    protected $dbQuery;

    /**
     * 成功返回Code码
     */
    static protected $successCode = "1";

    /**
     * 失败返回Code码
     */
    static protected $failCode = "0";

    /**
     * 定义返回码的数组名称
     */
    static protected $returnCodeName = [
        "codeName"      =>  "code",
        "dataName"      =>  "data",
        "messageName"   =>  "info"
    ];

    /**
     * 定义返回码的massage名称
     */
    static protected $returnCode = [
        '1' => '操作成功',
        '0' => '操作失败',
        '1002' => '你想做什么呢', //非法的请求方式 非ajax
        '1003' => '请求参数错误', //如参数不完整,类型不正确
        '1004' => '请先登陆再访问', //未登录 或者 未授权
        '1005' => '请求授权不符', ////非法的请求  无授权查看
        '1006' => '数据加载失败', //
        '1007' => '数据修改失败', //
        '1008' => '系统错误', //
        '1010' => '数据不存在', //
        '1020' => '验证码输入不正确', //
        '1021' => '用户账号或密码错误', //
        '1022' => '用户账号被禁用', //
        '1030' => '数据操作失败', //
    ];

    /**
     * Service constructor.
     */
    public function __construct()
    {
        parent::__construct();
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
     * 成功返回
     * @param string $msg
     * @param string $data
     * @param array $header
     * @param string $code
     */
    public function successReturn($msg = '', $data = '', array $header = [], $code = "")
    {
        $code = is_string($code) && !empty($code) ? $code : self::$successCode;
        $this->showReturn($code, $msg, $data, $header);
    }

    /**
     * 失败返回
     * @param string $msg
     * @param string $data
     * @param array $header
     * @param string $code
     */
    public function failReturn($msg = '', $data = '', array $header = [], $code = "")
    {
        $code = is_string($code) && !empty($code) ? $code : self::$failCode;
        $this->showReturn($code, $msg, $data, $header);
    }

    /**
     * 返回基础方法
     * @param $code
     * @param $msg
     * @param $data
     * @param array $header
     */
    protected function showReturn($code, $msg, $data, array $header = [])
    {
        $result = [
            self::$returnCodeName["codeName"] => $code,
            self::$returnCodeName["messageName"] => $msg,
            self::$returnCodeName["dataName"] => $data
        ];
        $config = $this->app['config'];
        $type = $config->get('default_ajax_return');
        $response = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }


    /**
     * 指定获取已注入依赖
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
     * 魔术方法,指定获取已注入依赖
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
     * 魔术方法,指定获取已注入依赖
     * @param $name
     * @return mixed
     * @throws Exception
     */
    public function __get($name)
    {
        return $this->_load($name);
    }

    /**
     * 快捷查询逻辑器
     * @param string|Query $dbQuery
     * @return ApiQueryHelper|Model
     */
    protected function _query($dbQuery)
    {
        $this->dbQuery = $dbQuery;
        return ApiQueryHelper::instance()->init($dbQuery);
    }

    /**
     * 快捷分页逻辑器
     * @param string|Query $dbQuery
     * @param boolean $page 是否启用分页
     * @param integer $limit 集合每页记录数
     * @return array
     */
    protected function _page($dbQuery, $page = true, $limit = 0)
    {
        return ApiPageHelper::instance()->init($dbQuery, $page, $limit);
    }

    /**
     * 快捷输入并验证
     * @param $params
     * @param array $rule 验证规则
     * @param array $info 验证消息
     * @return array
     */
    protected function _input($params, $rule = [], $info = [])
    {
        return ApiInputHelper::instance()->init($params, $rule, $info);
    }
}
