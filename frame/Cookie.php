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

namespace fxyin;

/**
 * Cookie类
 */
class Cookie
{
    /**
     * 获取数据
     * @param string $name 名称
     * @return mixed
     */
    public static function get($name = null)
    {
        // 获取所有
        if (is_null($name)) return $_COOKIE;
        // 检查数据
        if (!isset($_COOKIE[$name])) return;
        // 获取数据
        return $_COOKIE[$name];
    }

    /**
     * 设置数据
     * @param array $vars 参数
     * @return void
     */
    public static function set(...$vars)
    {
        // 初始化变量
        $config = fxy_config('app.cookie');
        $predefined = [
            // 键名
            0 => null,
            // 键值
            1 => '',
            // 保存时间
            2 => $config['expire'],
            // 保存路径
            3 => $config['path'],
            // 有效域名
            4 => $config['domain'],
            // 启用安全传输
            5 => $config['secure'],
            // httponly设置
            6 => $config['httponly'],
        ];
        $vars = \fxapp\Param::define([$vars, $predefined], '1.1.1');
        if (is_null($vars[0])) return false;
        $vars[2] = !empty($vars[2]) ? $_SERVER['REQUEST_TIME'] + intval($vars[2]) : 0;
        setcookie(...$vars);
        // 设置Cookie
        $_COOKIE[$vars[0]] = $vars[1];
        return true;
    }
}
