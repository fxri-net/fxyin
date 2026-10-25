<?php
// +----------------------------------------------------------------------
// | Name 风音框架
// +----------------------------------------------------------------------
// | Author 唐启云 <tqy@fxri.net>
// +----------------------------------------------------------------------
// | Copyright Copyright © 2016-2099 方弦研究所. All rights reserved.
// +----------------------------------------------------------------------
// | Link https://www.fxri.net
// +----------------------------------------------------------------------

namespace fxapp;

/**
 * 数据类
 * @see \fxapp\facade\Data
 * @package fxapp\facade
 * @method static array where(array $var, array $param) SQL-条件
 * @method static string whereSafe(string $var, string &$method, int $case = 0) SQL-条件安检
 * @method static string whereFilter(string $var, string $method, int $case = 0) SQL-条件过滤
 * @method static string whereBuild(array|string $key, string $value, string $method) SQL-条件搭建
 * @method static string whereAssemble(array $array) SQL-条件装配
 * @method static string whereMake(array $var, int $type = 1) SQL-条件组装
 * @method static bool|string insert(string $table, array $data) SQL-插入
 * @method static bool|string update(string $table, array $data, array $param = []) SQL-更新
 * @method static array updateContrast(array $data_new, array $data_old) 处理参数-更新比较
 * @method static string storeUpperLevel(string $table_name, int $link_id = 0, string $link_name = 'parent_id', string $index = 'id') 处理存储-递归上级
 * @method static string storeLowerLevel(string $table_name, int $link_id = 0, string $link_name = 'parent_id', string $index = 'id') 处理存储-递归下级
 * @method static string fieldDistance(string $lngs, string $lats, string $lnge, string $late) 处理字段-计算经纬度距离
 * @method static string fieldInitial(string $field) 处理字段-首字母
 * @method static string fieldDivision(string $dividend, string $divisor) 处理字段-除法
 * @method static string fieldJson(string $field, string $replace = '', int $param = 1) 处理字段-Json
 * @method static string fieldText(string $field, string $replace = '', int $param = 1) 处理字段-文本
 * @method static string fieldDate(string $field, string $replace = '', int|string $param = 1) 处理字段-日期
 * @method static string fieldAlias(string $data, string $alias, string $delimiter = '.') 处理字段-别名
 * @method static string fieldEscape(string $data, string $delimiter = '.') 处理字段-转义
 * @method static mixed htmlFilter(mixed $data, ?string $flags = null) 处理HTML-过滤
 * @method static mixed htmlRemove(mixed $data, ?string $flags = null) 处理HTML-移除
 * @method static array listToTree(array $list, int $parent_id = 0, string $parnet_name = 'parent_id', string $index = 'id', string $child_name = '_child') 列表格式树
 * @method static array paramRepair(array $param) 处理参数-补全
 * @method static array paramFilter(array $param) 处理参数-过滤
 * @method static array paramEmpty(array $param) 检查参数-空参
 * @method static array paramExist(array $param) 检查参数-非空
 */
class Data extends \fxyin\Facade
{
}
