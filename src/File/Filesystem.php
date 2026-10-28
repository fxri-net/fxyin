<?php

declare(strict_types=1);

namespace Fxri\File;

use RuntimeException;

/**
 * 文件系统操作。
 *
 * 写文件先落临时文件再改名，避免中途失败留下半截内容。
 */
final class Filesystem
{
    /**
     * 递归创建目录。
     *
     * @param string $directory 目录路径
     * @param int $permissions 权限位
     * @return void
     */
    public static function createDirectory(string $directory, int $permissions = 0o777): void
    {
        self::makeDirectory($directory, $permissions);
    }

    /**
     * 递归删除目录。
     *
     * @param string $directory 目录路径
     * @return void
     */
    public static function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $names = scandir($directory);
        if (false === $names) {
            throw new RuntimeException('无法读取目录：' . $directory);
        }

        foreach ($names as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path) && !is_link($path)) {
                self::deleteDirectory($path);

                continue;
            }
            if (!unlink($path)) {
                throw new RuntimeException('无法删除文件：' . $path);
            }
        }

        if (!rmdir($directory)) {
            throw new RuntimeException('无法删除目录：' . $directory);
        }
    }

    /**
     * 判断路径是否存在。
     *
     * @param string $path 路径
     * @return bool 是否存在
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * 递归列出目录下的文件。
     *
     * 只返回文件，不返回目录；`$maxDepth` 为 null 时不限深度，0 只取当前层，
     * 正数表示向下递归的层数。扩展名留空表示不过滤，匹配不区分大小写。
     *
     * 符号链接目录不跟随，避免环形目录导致无限递归；结果按路径排序，保证输出稳定。
     *
     * @param string $directory 目录路径
     * @param list<string> $extensions 扩展名列表，不含点号
     * @param int|null $maxDepth 递归深度
     * @return list<string> 文件路径
     */
    public static function findFiles(string $directory, array $extensions = [], ?int $maxDepth = null): array
    {
        $path = realpath($directory);
        if (false === $path || !is_dir($path)) {
            return [];
        }

        $files = self::collect($path, self::normalizeExtensions($extensions), $maxDepth, 0);
        sort($files);

        return $files;
    }

    /**
     * 列出目录下的文件与子目录，只取当前层。
     *
     * @param string $directory 目录路径
     * @return list<string> 路径列表
     */
    public static function listFiles(string $directory): array
    {
        $items = glob(rtrim($directory, '/\\') . '/*');

        return false === $items ? [] : $items;
    }

    /**
     * 递归创建目录。
     *
     * @param string $directory 目录路径
     * @param int $permissions 权限位
     * @return void
     */
    public static function makeDirectory(string $directory, int $permissions = 0o777): void
    {
        if (is_dir($directory)) {
            return;
        }

        // 并发场景下 mkdir 可能因竞争失败，此时目录已由其他进程建好
        if (!mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目录：' . $directory);
        }
    }

    /**
     * 移动目录。
     *
     * @param string $source 源目录
     * @param string $destination 目标目录
     * @return void
     */
    public static function moveDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new RuntimeException('待移动的目录不存在：' . $source);
        }

        self::makeDirectory(dirname($destination));
        if (!rename($source, $destination)) {
            throw new RuntimeException('无法移动目录：' . $source . ' → ' . $destination);
        }
    }

    /**
     * 读取文件内容。
     *
     * @param string $path 文件路径
     * @return string 文件内容
     */
    public static function read(string $path): string
    {
        $contents = file_get_contents($path);
        if (false === $contents) {
            throw new RuntimeException('无法读取文件：' . $path);
        }

        return $contents;
    }

    /**
     * 写入文件内容。
     *
     * @param string $path 文件路径
     * @param string $contents 文件内容
     * @return void
     */
    public static function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        self::makeDirectory($directory);

        $temporary = tempnam($directory, '.fxri');
        if (false === $temporary) {
            throw new RuntimeException('无法创建临时文件：' . $directory);
        }
        if (false === file_put_contents($temporary, $contents)) {
            unlink($temporary);

            throw new RuntimeException('无法写入文件：' . $path);
        }
        if (!rename($temporary, $path)) {
            unlink($temporary);

            throw new RuntimeException('无法替换文件：' . $path);
        }
    }

    /**
     * 递归收集文件。
     *
     * @param string $directory 当前目录
     * @param list<string> $extensions 扩展名列表
     * @param int|null $maxDepth 递归深度
     * @param int $level 当前层级
     * @return list<string> 文件路径
     */
    private static function collect(string $directory, array $extensions, ?int $maxDepth, int $level): array
    {
        $names = scandir($directory);
        if (false === $names) {
            throw new RuntimeException('无法读取目录：' . $directory);
        }

        $files = [];

        foreach ($names as $name) {
            if ('.' === $name || '..' === $name) {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                if (self::matches($path, $extensions)) {
                    $files[] = $path;
                }

                continue;
            }

            // 符号链接目录不跟随，避免环形目录导致无限递归
            if (is_dir($path) && !is_link($path) && (null === $maxDepth || $level < $maxDepth)) {
                $files = array_merge($files, self::collect($path, $extensions, $maxDepth, $level + 1));
            }
        }

        return $files;
    }

    /**
     * 判断文件扩展名是否命中。
     *
     * @param string $path 文件路径
     * @param list<string> $extensions 扩展名列表
     * @return bool 是否命中
     */
    private static function matches(string $path, array $extensions): bool
    {
        if ([] === $extensions) {
            return true;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true);
    }

    /**
     * 归一扩展名。
     *
     * @param list<string> $extensions 扩展名列表
     * @return list<string> 去点号、小写、去重后的列表
     */
    private static function normalizeExtensions(array $extensions): array
    {
        $normalized = [];

        foreach ($extensions as $extension) {
            $value = strtolower(ltrim(trim($extension), '.'));
            if ('' !== $value) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }
}
