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
 * 加载器类
 */
class Loader
{
    /**
     * 自动加载
     * @param string $class 类名
     * @return mixed
     */
    public static function autoload($class)
    {
        // 初始化变量
        $name = explode('\\', $class);
        // 获取加载器配置
        $config = $class != 'fxyin\\Config' ? Config::get('loader') : null;
        if (empty($config)) {
            $config = (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php')['loader'] ?? [];
        }
        $config = $config[$name[0]] ?? null;
        if (!is_array($config)) return;
        ksort($config);
        // 执行加载器
        array_shift($name);
        $name = implode(DIRECTORY_SEPARATOR, $name);
        foreach ($config as $elem) {
            $elem = realpath($elem);
            if (false === $elem) return;
            $file = $elem . DIRECTORY_SEPARATOR . $name . '.php';
            if (is_file($file)) {
                return require $file;
            }
        }
    }

    /**
     * 注册自动加载机制
     * @return void
     */
    public static function register($autoload = '')
    {
        // 注册加载器
        spl_autoload_register($autoload ?: 'fxyin\\Loader::autoload', true, false);
    }
}
