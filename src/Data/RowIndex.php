<?php

declare(strict_types=1);

namespace Fxri\Data;

use InvalidArgumentException;

/**
 * 扁平列表的主键索引。
 *
 * `Hierarchy` 与 `Tree` 共用同一份主键校验与索引规则，避免两处判定漂移。
 *
 * @internal 实现细节，不对外承诺
 */
final class RowIndex
{
    /**
     * 按主键建立索引。
     *
     * @param array<int, mixed> $list 扁平列表
     * @param string $idKey 主键字段名
     * @return array<array-key, array<array-key, mixed>> 主键索引
     */
    public static function keyBy(array $list, string $idKey): array
    {
        $indexed = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException('列表项必须是数组');
            }

            $id = $row[$idKey] ?? null;
            if (!is_int($id) && !is_string($id)) {
                throw new InvalidArgumentException('列表项缺少主键：' . $idKey);
            }

            $indexed[$id] = $row;
        }

        return $indexed;
    }
}
