<?php

declare(strict_types=1);

namespace Fxri\Param;

use Fxri\Support\Type;

/**
 * 数组合并。
 *
 * 只做合并，不修改入参；深度为 0 时整体覆盖，深度为负数时按需无限递归。
 */
final class Merger
{
    /**
     * 递归合并，覆盖层的同名值优先。
     *
     * @param array<array-key, mixed> $base 基底
     * @param array<array-key, mixed> $overlay 覆盖层
     * @param int $depth 递归深度，负数表示不限制
     * @return array<array-key, mixed> 合并结果
     */
    public static function merge(array $base, array $overlay, int $depth = -1): array
    {
        foreach ($overlay as $key => $value) {
            if (is_array($value) && 0 !== $depth && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::merge($base[$key], $value, $depth > 0 ? $depth - 1 : -1);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * 递归合并，覆盖层中的空值不覆盖基底已有值。
     *
     * @param array<array-key, mixed> $base 基底
     * @param array<array-key, mixed> $overlay 覆盖层
     * @param int $depth 递归深度，负数表示不限制
     * @return array<array-key, mixed> 合并结果
     */
    public static function mergeKeepBlank(array $base, array $overlay, int $depth = -1): array
    {
        foreach ($overlay as $key => $value) {
            if (Type::isBlank($value) && array_key_exists($key, $base)) {
                continue;
            }
            if (is_array($value) && 0 !== $depth && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeKeepBlank($base[$key], $value, $depth > 0 ? $depth - 1 : -1);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
