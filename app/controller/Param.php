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
 * 参数类
 * @see \fxapp\facade\Param
 * @package fxapp\facade
 * @method static mixed init() 初始化配置
 * @method static array define(array $param, ?int $mode = null) 定义参数
 * @method static array array(array $param, int $limit = -1, bool $filter = true) 数组填充空值
 * @method static mixed object(array $param, int $limit = -1, bool $filter = true) 空数组转对象
 * @method static array pick(array $base, array $data) 提取数组
 * @method static mixed append(array ...$args) 追加数组
 * @method static mixed merge(array|int $limit = -1, boolean|string $cover = true, boolean $type = false, array ...$args) 合并数组
 * @method static array cover(array $args, int $limit = -1, boolean|string $cover = true, boolean $type = false) 覆盖数组
 * @method static mixed url(array $data) 设置/获取URL
 * @method static mixed json(mixed $var, string $type, mixed $param = null) 解析Json
 */
class Param extends \fxyin\Facade
{
}
