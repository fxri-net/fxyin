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
 * 基础类
 * @see \fxapp\facade\Base
 * @package fxapp\facade
 * @method static boolean load(string $file, ?string $type = null) 加载文件
 * @method static string lang(array|string $name, array $vars = [], string $lang = '') 语言
 * @method static string langList(array|string $name, array $vars = [], string $lang = '') 语言-列表
 * @method static string langParse(array|string $name) 语言-解析
 * @method static mixed config(array ...$vars) 配置参数-[获取|设置]
 * @method static mixed cookie(array ...$vars) 配置Cookie-[获取|设置]
 * @method static mixed env(array ...$vars) 环境参数-[获取|设置]
 * @method static void dump(array ...$vars) 浏览器友好的变量输出
 * @method static mixed json(mixed $var, string $type, mixed $param = null) 解析Json
 * @method static mixed ipv4(mixed $data, string $type) 解析IPv4
 * @method static mixed crypt(mixed $var, string $type, ?string $param = null) 加密解密
 * @method static void throwable(string $message) 抛出异常
 * @method static array echo(string $message, int|null $code = null, array $data = [], array $extend = []) 配置响应
 */
class Base extends \fxyin\Facade
{
}
