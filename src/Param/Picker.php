<?php

declare(strict_types=1);

namespace Fxri\Param;

use Fxri\Support\Type;

/**
 * 取值工具。
 */
final class Picker
{
    /**
     * 按给定结构提取，缺失键补 null。
     *
     * 键为数组时递归下钻，用于按嵌套结构提取；其余键按 1.x `Param::pick()` 的语义
     * 补 null，而不是像 `only()` 那样直接跳过。
     *
     * @param array<array-key, mixed> $source 数据源
     * @param array<array-key, mixed> $keys 提取结构
     * @return array<array-key, mixed> 提取结果
     */
    public static function pick(array $source, array $keys): array
    {
        $result = [];

        foreach ($keys as $key => $value) {
            if (is_array($value)) {
                $child = $source[$key] ?? null;
                $result[$key] = self::pick(is_array($child) ? $child : [], $value);

                continue;
            }

            $name = Type::toString($value);
            $result[$name] = array_key_exists($name, $source) ? $source[$name] : null;
        }

        return $result;
    }

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
