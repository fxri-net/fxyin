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
 * 应用类
 */
class App
{
    /**
     * 初始化系统
     * @param string $name 名称
     * @return boolean
     */
    public static function init($name = null)
    {
        // 初始化变量
        static $data = ['load' => [], 'start' => []];
        $config = Config::get('env.app');
        if (!is_array($config)) return false;
        if (isset($config[$name])) {
            $config = [$name => $config[$name]];
        }
        ksort($config);
        // 加载应用
        foreach ($config as $key => $value) {
            if (array_key_exists($key, $data['load'])) continue;
            self::load($value);
            $data['load'][$key] = $value;
        }
        // 启动应用
        foreach ($config as $key => $value) {
            if (array_key_exists($key, $data['start'])) continue;
            self::start($value);
            $data['start'][$key] = $value;
        }
        return true;
    }

    /**
     * 加载应用
     * @param string $path 路径
     * @return mixed
     */
    private static function load($path = null)
    {
        // 初始化变量
        $path = realpath($path);
        if (false === is_dir($path)) return;
        $path .= DIRECTORY_SEPARATOR;
        // 加载函数文件
        if (is_dir($path . 'function')) {
            $dir = $path . 'function';
            $files = \fxapp\File::getList($dir, 'php');
            foreach ($files as $file) {
                require $file;
            }
        }
        // 加载配置文件
        if (is_dir($path . 'config')) {
            $dir = $path . 'config';
            $files = \fxapp\File::getList($dir, 'php');
            foreach ($files as $file) {
                $name = str_replace($dir, '', $file);
                $name = str_replace(DIRECTORY_SEPARATOR, '.', $name);
                $name = substr($name, 1, strrpos($name, '.php') - 1);
                Config::load($file, $name);
            }
        }
    }

    /**
     * 启动应用
     * @param string $path 路径
     * @return mixed
     */
    private static function start($path = null)
    {
        // 初始化变量
        $path = realpath($path);
        if (false === is_dir($path)) return;
        $path .= DIRECTORY_SEPARATOR;
        // 加载语言包
        if (is_dir($path . 'language')) {
            $config = fxy_config('app.lang');
            $lang = fxy_config('env.view.lang');
            $dir = $path . 'language' . DIRECTORY_SEPARATOR . $lang;
            if (!is_dir($dir)) {
                $lang = $config['default'];
            }
            Lang::range($lang);
            $dir = $path . 'language' . DIRECTORY_SEPARATOR . $lang;
            if (is_dir($dir)) {
                $files = \fxapp\File::getList($dir, 'php');
                foreach ($files as $file) {
                    Lang::load($file, $lang);
                }
            }
        }
        // 加载商店文件
        if (is_dir($path . 'store')) {
            $dir = $path . 'store';
            $files = \fxapp\File::getList($dir, 'php');
            foreach ($files as $file) {
                require $file;
            }
        }
    }

    /**
     * 运行应用
     * @return void
     */
    public static function run()
    {
        // 初始化变量
        $class = $_SERVER['REQUEST_URI'] ?? null;
        // 解析路由
        $class = str_replace('/', '\\', $class);
        $class = substr($class, 1);
        $class = explode('\\', $class);
        $method = array_pop($class);
        $class = explode('\\', strtolower(implode('\\', $class)));
        $class[] = ucfirst(array_pop($class));
        // 解析控制器
        $app = fxy_config('env.base.name') ?: 'app';
        $class = '\\' . $app . '\\' . implode('\\', $class);
        if (!class_exists($class)) {
            \fxapp\Base::throwable('控制器不存在', '1.1');
        };
        $class = new $class();
        // 解析方法
        if (!is_callable([$class, $method])) {
            \fxapp\Base::throwable('方法不存在', '1.1');
        }
        return call_user_func_array([$class, $method], []);
    }
}
