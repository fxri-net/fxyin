<?php

declare(strict_types=1);

namespace Fxri\File;

/**
 * PHP 文件加载器。
 *
 * 同一路径只执行一次；`load()` 用于副作用脚本，`read()` 用于返回数据的文件。
 */
final class Loader
{
    /** @var array<string, bool> 副作用脚本加载结果 */
    private static array $loaded = [];

    /** @var array<string, mixed> 数据文件读取结果 */
    private static array $data = [];

    /**
     * 加载脚本文件。
     *
     * 文件不存在时返回 false，且不会执行任何代码。
     *
     * @param string $file 文件路径
     * @return bool 是否加载成功
     */
    public static function load(string $file): bool
    {
        $path = self::path($file);

        if (!array_key_exists($path, self::$loaded)) {
            self::$loaded[$path] = is_file($path);
            if (self::$loaded[$path]) {
                require $path;
            }
        }

        return self::$loaded[$path];
    }

    /**
     * 读取数据文件。
     *
     * 文件不存在时返回 false；文件返回值原样保留，不做结构校验。
     *
     * @param string $file 文件路径
     * @return mixed 文件返回值
     */
    public static function read(string $file): mixed
    {
        $path = self::path($file);

        if (!array_key_exists($path, self::$data)) {
            self::$data[$path] = is_file($path) ? require $path : false;
        }

        return self::$data[$path];
    }

    /**
     * 归一文件路径。
     *
     * @param string $file 文件路径
     * @return string 真实路径，无法解析时返回原路径
     */
    private static function path(string $file): string
    {
        $path = realpath($file);

        return false === $path ? $file : $path;
    }
}
