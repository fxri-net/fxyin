<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 取值工具。
 */
final class Picker
{
    /**
     * 只保留指定键。
     *
     * @param array<array-key, mixed> $source 数据源
     * @param array<array-key, string> $keys 需要保留的键
     * @return array<array-key, mixed> 提取结果
     */
    public static function only(array $source, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $result[$key] = $source[$key];
            }
        }

        return $result;
    }

    /**
     * 按点分路径取值。
     *
     * @param array<array-key, mixed> $source 数据源
     * @param string $path 点分路径，如 user.name
     * @param mixed $default 取不到时的兜底值
     * @return mixed 取到的值
     */
    public static function pluck(array $source, string $path, mixed $default = null): mixed
    {
        $current = $source;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
