<?php

declare(strict_types=1);

namespace Fxri\Data;

/**
 * 参数规范化。
 *
 * 只做整理与检查，不修改原数组，返回结果可直接用于后续校验或入库。
 */
final class Param
{
    /**
     * 去除空值。
     *
     * @param array<array-key, mixed> $data 原始数据
     * @return array<array-key, mixed> 不含 null 的数据
     */
    public static function filter(array $data): array
    {
        return array_filter($data, static fn (mixed $value): bool => null !== $value);
    }

    /**
     * 找出缺失的键。
     *
     * @param array<array-key, mixed> $data 原始数据
     * @param NullPolicy $policy 空值判定策略
     * @return array<int, array-key> 缺失键名
     */
    public static function missing(array $data, NullPolicy $policy = NullPolicy::Blank): array
    {
        $missing = [];
        foreach ($data as $key => $value) {
            if ($policy->isEmpty($value)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }
}
