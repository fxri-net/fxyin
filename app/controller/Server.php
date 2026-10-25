<?php
// +----------------------------------------------------------------------
// | Name 风音框架
// +----------------------------------------------------------------------
// | Author 唐启云 <tqy@fxri.net>
// +----------------------------------------------------------------------
// | Copyright Copyright © 2016-2026 唐启云. All rights reserved.
// +----------------------------------------------------------------------
// | Link https://www.fxri.net
// +----------------------------------------------------------------------

namespace fxapp;

/**
 * 服务器类
 * @see \fxapp\facade\Server
 * @package fxapp\facade
 * @method static array echo() 初始化响应
 * @method static mixed ip() 获取IP
 * @method static mixed system(int $type = -1) 获取系统
 * @method static array format(array $data, int $type = 1) 处理数据-格式
 * @method static mixed env() 处理数据-环境
 * @method static mixed branch() 处理数据-分支
 */
class Server extends \fxyin\Facade
{
}
