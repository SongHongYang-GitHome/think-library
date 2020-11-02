<?php
/**
 * Desc: 数据层基类
 * Date: 2020-11-02
 */

namespace library;
use think\Container;
use think\db\Query;
use think\Exception;
use think\Model as ThinkModel;

class Model extends ThinkModel
{
    /**
     * 静态实例对象
     * @return object|Query|\think\Model|Model
     */
    public static function instance()
    {
        return Container::getInstance()->make(static::class);
    }

    /**
     * 快捷查询逻辑器
     * @return QueryHelper
     * @throws Exception
     */
    public function _query()
    {
        if (empty($this->table)) throw new Exception(get_called_class() . "未指定表名");
        return QueryHelper::instance()->init($this->table);
    }

    /**
     * 快捷分页逻辑器
     * @param boolean $page 是否启用分页
     * @param integer $limit 集合每页记录数
     * @return array
     * @throws Exception
     */
    public function _page($page = true, $limit = 0)
    {
        if (empty($this->table)) throw new Exception(get_called_class() . "未指定表名");
        return PageHelper::instance()->init($this->table, $page, $limit);
    }
}
