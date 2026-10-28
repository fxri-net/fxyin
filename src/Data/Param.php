<?php

declare(strict_types=1);

namespace Fxri\Data;

use InvalidArgumentException;

/**
 * 参数规范化。
 *
 * 只做整理与检查，不修改原数组，返回结果可直接用于后续校验或入库。
 */
final class Param
{
    /**
     * 判断是否至少存在一个非空值。
     *
     * @param array<array-key, mixed> $data 原始数据
     * @param NullPolicy $policy 空值判定策略
     * @return bool 是否存在非空值
     */
    public static function exist(array $data, NullPolicy $policy = NullPolicy::Blank): bool
    {
        foreach ($data as $value) {
            if (!$policy->isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

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

    /**
     * 补全多行数据的键。
     *
     * 收集全部行出现过的键，缺失处补空串，并按主键名排序，保证每行结构一致。
     *
     * @param array<int, mixed> $rows 数据行
     * @return array<int, array<array-key, mixed>> 补全后的数据行
     */
    public static function repair(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('数据行必须是数组');
            }
            foreach (array_keys($row) as $key) {
                $keys[$key] = true;
            }
        }

        $names = array_keys($keys);
        sort($names);

        $result = [];
        foreach ($rows as $row) {
            foreach ($names as $name) {
                if (!array_key_exists($name, $row)) {
                    $row[$name] = '';
                }
            }
            ksort($row);
            $result[] = $row;
        }

        return $result;
    }
}
