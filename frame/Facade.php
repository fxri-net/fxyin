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
 * 门面类
 */
class Facade
{
    /**
     * 数据
     * @var array
     */
    protected static $data;

    /**
     * 获取类名
     * @access protected
     * @return string
     */
    protected static function getClass()
    {
        // 初始化变量
        $class = explode('\\', static::class);
        return array_pop($class);
    }

    /**
     * 重载方法
     * @param string $name 名称
     * @param array $data 数据
     * @return mixed
     */
    public static function __callStatic($name, $data)
    {
        // 初始化变量
        $config = Env::get('facade');
        if (empty($config)) {
            $config = (require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config.php')['env']['facade'] ?? [];
        }
        ksort($config);
        foreach ($config as $elem) {
            $class = $elem . static::getClass();
            if (!isset(static::$data[$class])) {
                static::$data[$class] = class_exists($class) ? new $class() : new \stdClass();
            }
            if (is_callable([static::$data[$class], $name])) {
                return call_user_func_array([static::$data[$class], $name], $data);
            }
        }
        throw new \Exception(fxy_lang(['方法', ' \'', static::class . '::' . $name, '\' ', '不存在']));
    }
}
