<?php
/**
 * Desc: 快捷查询
 * Date: 2020-11-02
 */

namespace library\helper;

use think\App;
use think\Request;
use library\ApiController;
use think\Container;
use think\Db;


class ApiQueryHelper
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
     * Query call.
     * @param string $name 调用方法名称
     * @param array $args 调用参数内容
     * @return QueryHelper
     */
    public function __call($name, $args)
    {
        if (is_callable($callable = [$this->query, $name])) {
            call_user_func_array($callable, $args);
        }
        return $this;
    }

    /**
     * 逻辑器初始化
     * @param string|Query $dbQuery
     * @return $this
     */
    public function init($dbQuery)
    {
        $this->query = $this->buildQuery($dbQuery);
        return $this;
    }

    /**
     * 获取当前Db操作对象
     * @return Query
     */
    public function db()
    {
        return $this->query;
    }

    /**
     * 设置Like查询条件
     * @param string|array $fields 查询字段
     * @param string $input 输入类型 get|post
     * @param string $alias 别名分割符
     * @return $this
     */
    public function like($fields, $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereLike($dk, "%{$data[$qk]}%");
            }
        }
        return $this;
    }

    /**
     * 设置Equal查询条件
     * @param string|array $fields 查询字段
     * @param string $input 输入类型 get|post
     * @param string $alias 别名分割符
     * @return $this
     */
    public function equal($fields, $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->where($dk, "{$data[$qk]}");
            }
        }
        return $this;
    }

    /**
     * 设置IN区间查询
     * @param string $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型 get|post
     * @param string $alias 别名分割符
     * @return $this
     */
    public function in($fields, $split = ',', $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereIn($dk, explode($split, $data[$qk]));
            }
        }
        return $this;
    }

    /**
     * 设置find_in_set区间查询
     * @param string $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型 get|post
     * @param string $alias 别名分割符
     * @return $this
     */
    public function inSet($fields, $split = ',', $input = 'request', $alias = '#')
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                $this->query->whereRaw("FIND_IN_SET($data[$qk],$field)");
            }
        }
        return $this;
    }

    /**
     * 设置内容区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型 get|post
     * @param string $alias 别名分割符
     * @return $this
     */
    public function valueBetween($fields, $split = ' ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias);
    }

    /**
     * 设置日期时间区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型
     * @param string $alias 别名分割符
     * @return $this
     */
    public function dateBetween($fields, $split = ' - ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if ($type === 'after') {
                return "{$value} 23:59:59";
            } else {
                return "{$value} 00:00:00";
            }
        });
    }

    /**
     * 设置时间戳区间查询
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型
     * @param string $alias 别名分割符
     * @return $this
     */
    public function timeBetween($fields, $split = ' - ', $input = 'request', $alias = '#')
    {
        return $this->setBetweenWhere($fields, $split, $input, $alias, function ($value, $type) {
            if ($type === 'after') {
                return strtotime("{$value} 23:59:59");
            } else {
                return strtotime("{$value} 00:00:00");
            }
        });
    }

    /**
     * 设置区域查询条件
     * @param string|array $fields 查询字段
     * @param string $split 输入分隔符
     * @param string $input 输入类型
     * @param string $alias 别名分割符
     * @param callable $callback
     * @return $this
     */
    private function setBetweenWhere($fields, $split = ' ', $input = 'request', $alias = '#', $callback = null)
    {
        $data = $this->app->request->$input();
        foreach (is_array($fields) ? $fields : explode(',', $fields) as $field) {
            list($dk, $qk) = [$field, $field];
            if (stripos($field, $alias) !== false) {
                list($dk, $qk) = explode($alias, $field);
            }
            if (isset($data[$qk]) && $data[$qk] !== '') {
                list($begin, $after) = explode($split, $data[$qk]);
                if (is_callable($callback)) {
                    $after = call_user_func($callback, $after, 'after');
                    $begin = call_user_func($callback, $begin, 'begin');
                }
                $this->query->whereBetween($dk, [$begin, $after]);
            }
        }
        return $this;
    }

    /**
     * 实例化分页管理器
     * @param boolean $page 是否启用分页
     * @param integer $limit 集合每页记录数
     * @return mixed
     */
    public function page($page = true, $limit = 0)
    {
        return ApiPageHelper::instance()->init($this->query, $page, $limit);
    }

    /**
     * 获取数据库对象
     * @param string|Query $dbQuery
     * @return Query
     */
    protected function buildQuery($dbQuery)
    {
        return is_string($dbQuery) ? Db::name($dbQuery) : $dbQuery;
    }

    /**
     * 实例对象反射
     * @return static
     */
    public static function instance()
    {
        return Container::getInstance()->invokeClass(static::class);
    }
}
