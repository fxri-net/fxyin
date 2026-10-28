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
}
