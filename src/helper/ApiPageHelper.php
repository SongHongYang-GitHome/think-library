<?php
/**
 * Desc: 快捷分页
 * Date: 2020-11-02
 */

namespace library\helper;

use think\App;
use think\Container;
use think\Db;

class ApiPageHelper
{
    /**
     * 当前应用容器
     * @var App
     */
    public $app;

    /**
     * 数据库实例
     * @var Query
     */
    public $query;

    /**
     * 是否启用分页
     * @var boolean
     */
    protected $page;

    /**
     * 集合分页记录数
     * @var integer
     */
    protected $total;

    /**
     * 集合每页记录数
     * @var integer
     */
    protected $limit;

    /**
     * Helper constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        $this->app = $app;
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

    /**
     * 逻辑器初始化
     * @param string|Query $dbQuery
     * @param boolean $page 是否启用分页
     * @param integer $limit 集合每页记录数
     * @return array|mixed
     */
    public function init($dbQuery, $page = true, $limit = 0)
    {
        $this->page = $page;
        $this->total = false;
        $this->limit = $limit;
        $this->query = $this->buildQuery($dbQuery);
        // 未配置 order 规则时自动按 sort 字段排序
        if (!$this->query->getOptions('order') && method_exists($this->query, 'getTableFields')) {
            if (in_array('sort', $this->query->getTableFields())) $this->query->order('sort desc');
        }
        // 列表分页及结果集处理
        if ($this->page) {
            $limit = ($this->limit > 0) ? $this->limit : 20;
            $page = $this->query->paginate($limit, $this->total);
            $result = ['page' => ['limit' => intval($limit), 'total' => intval($page->total()), 'pages' => intval($page->lastPage()), 'current' => intval($page->currentPage())], 'list' => $page->items()];
        } else {
            $result = ['list' => $this->query->select()];
        }
        return $result;
    }
}
