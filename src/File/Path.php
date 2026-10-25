<?php

declare(strict_types=1);

namespace Fxri\File;

/**
 * 路径处理。
 *
 * 统一使用正斜杠表示层级，规范化时保留协议头与盘符前缀。
 */
final class Path
{
    /**
     * 取小写扩展名。
     *
     * @param string $path 路径
     * @return string 扩展名，无扩展名时为空串
     */
    public static function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * 取不含目录与扩展名的文件名。
     *
     * @param string $path 路径
     * @return string 文件名
     */
    public static function filename(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * 拼接路径片段。
     *
     * 首段保留前导斜杠与盘符、协议头，绝对路径拼接后仍是绝对路径。
     *
     * @param string ...$segments 路径片段
     * @return string 拼接并规范化后的路径
     */
    public static function join(string ...$segments): string
    {
        $parts = [];
        $absolute = false;
        foreach ($segments as $index => $segment) {
            $segment = str_replace('\\', '/', $segment);
            // 首段保留前导斜杠（绝对路径、盘符、协议头），其余段两端都去，避免拼出双斜杠
            if (0 === $index) {
                $absolute = str_starts_with($segment, '/');
                $segment = rtrim($segment, '/');
            } else {
                $segment = trim($segment, '/');
            }

            if ('' !== $segment) {
                $parts[] = $segment;
            }
        }

        $joined = implode('/', $parts);
        // normalize() 摘掉前缀后才判断绝对性，首段为空时前导斜杠会丢，需在此补回
        if ($absolute && !str_starts_with($joined, '/')) {
            $joined = '/' . $joined;
        }

        return self::normalize($joined);
    }

    /**
     * 规范化路径。
     *
     * @param string $path 路径
     * @return string 规范化结果
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        // 先摘出协议头或盘符，避免前缀被 .. 消解掉
        $prefix = '';
        if (1 === preg_match('#^([a-zA-Z][a-zA-Z0-9+.-]*://)#', $path, $matches)) {
            $prefix = $matches[1];
        } elseif (1 === preg_match('#^([a-zA-Z]:/)#', $path, $matches)) {
            $prefix = $matches[1];
        }

        $rest = substr($path, strlen($prefix));
        $absolute = '' !== $rest && '/' === $rest[0];

        $segments = [];
        foreach (explode('/', $rest) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }
            if ('..' === $segment) {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode('/', $segments);
        if ($absolute) {
            $normalized = '/' . $normalized;
        }

        return $prefix . $normalized;
    }
}
