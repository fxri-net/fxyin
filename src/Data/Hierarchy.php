<?php

declare(strict_types=1);

namespace Fxri\Data;

use InvalidArgumentException;

/**
 * 层级关系。
 *
 * 从扁平列表计算祖先与后代，父级缺失、自引用与环状关系都在遍历中截断。
 */
final class Hierarchy
{
    /**
     * 取祖先主键。
     *
     * 从直接父级开始向上，直到根节点；不含传入节点本身。
     *
     * @param array<int, mixed> $list 扁平列表
     * @param int|string $id 起始节点主键
     * @param string $idKey 主键字段名
     * @param string $parentKey 父键字段名
     * @return list<int|string> 祖先主键，由近及远
     */
    public static function ancestors(array $list, int|string $id, string $idKey = 'id', string $parentKey = 'parent_id'): array
    {
        $indexed = RowIndex::keyBy($list, $idKey);
        $result = [];
        $visited = [$id => true];
        $current = $id;

        while (array_key_exists($current, $indexed)) {
            $parent = $indexed[$current][$parentKey] ?? null;
            if (null === $parent || $parent === $current) {
                break;
            }
            if (!is_int($parent) && !is_string($parent)) {
                throw new InvalidArgumentException('父级主键必须是整数或字符串');
            }
            if (!array_key_exists($parent, $indexed) || isset($visited[$parent])) {
                break;
            }

            $result[] = $parent;
            $visited[$parent] = true;
            $current = $parent;
        }

        return $result;
    }

    /**
     * 取后代主键。
     *
     * 按层级广度优先展开，不含传入节点本身；重复与环状引用只记录一次。
     *
     * @param array<int, mixed> $list 扁平列表
     * @param int|string $id 起始节点主键
     * @param string $idKey 主键字段名
     * @param string $parentKey 父键字段名
     * @return list<int|string> 后代主键
     */
    public static function descendants(array $list, int|string $id, string $idKey = 'id', string $parentKey = 'parent_id'): array
    {
        $indexed = RowIndex::keyBy($list, $idKey);
        $children = [];
        foreach ($indexed as $key => $row) {
            $parent = $row[$parentKey] ?? null;
            if (null === $parent) {
                continue;
            }
            if (!is_int($parent) && !is_string($parent)) {
                throw new InvalidArgumentException('父级主键必须是整数或字符串');
            }
            $children[$parent][] = $key;
        }

        $result = [];
        $visited = [$id => true];
        $queue = [$id];
        for ($index = 0; $index < count($queue); ++$index) {
            $current = $queue[$index];

            foreach ($children[$current] ?? [] as $child) {
                if (isset($visited[$child])) {
                    continue;
                }

                $visited[$child] = true;
                $result[] = $child;
                $queue[] = $child;
            }
        }

        return $result;
    }
}
