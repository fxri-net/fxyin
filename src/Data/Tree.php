<?php

declare(strict_types=1);

namespace Fxri\Data;

use InvalidArgumentException;

/**
 * 树形结构。
 *
 * 扁平列表与树形结构互转；父级缺失或指向自身的节点保留为根，保证往返不丢数据。
 */
final class Tree
{
    /**
     * 列表转树。
     *
     * @param array<int, mixed> $list 扁平列表
     * @param int|string $rootId 根节点的父级标识
     * @param string $idKey 主键字段名
     * @param string $parentKey 父键字段名
     * @param string $childKey 子级字段名
     * @return array<int, array<array-key, mixed>> 树形结构
     */
    public static function fromList(
        array $list,
        int|string $rootId = 0,
        string $idKey = 'id',
        string $parentKey = 'parent_id',
        string $childKey = '_child',
    ): array {
        $indexed = RowIndex::keyBy($list, $idKey);

        $children = [];
        $roots = [];
        foreach ($indexed as $id => $row) {
            $parent = $row[$parentKey] ?? null;
            if (null !== $parent && !is_int($parent) && !is_string($parent)) {
                throw new InvalidArgumentException('父级主键必须是整数或字符串');
            }

            // 父级不存在或指向自己时按根节点处理，孤儿数据不会消失
            if (null === $parent || $parent === $id || $parent === $rootId || !array_key_exists($parent, $indexed)) {
                $roots[] = $id;
                continue;
            }

            $children[$parent][] = $id;
        }

        $tree = [];
        $visited = [];
        foreach ($roots as $id) {
            $tree[] = self::attach($id, $indexed, $children, $childKey, $visited);
        }

        // 环状父子关系没有入口，兜底提到顶层
        foreach (array_keys($indexed) as $id) {
            if (!isset($visited[$id])) {
                $tree[] = self::attach($id, $indexed, $children, $childKey, $visited);
            }
        }

        return $tree;
    }

    /**
     * 树转列表。
     *
     * @param array<array-key, mixed> $tree 树形结构
     * @param string $childKey 子级字段名
     * @return array<int, array<array-key, mixed>> 扁平列表，按前序排列
     */
    public static function toList(array $tree, string $childKey = '_child'): array
    {
        $list = [];
        self::flatten($tree, $childKey, $list);

        return $list;
    }

    /**
     * 递归挂载子级。
     *
     * @param int|string $id 节点主键
     * @param array<array-key, array<array-key, mixed>> $indexed 主键索引
     * @param array<array-key, array<int, int|string>> $children 父级到子级的索引
     * @param string $childKey 子级字段名
     * @param array<array-key, bool> $visited 已挂载标记
     * @return array<array-key, mixed> 节点
     */
    private static function attach(
        int|string $id,
        array $indexed,
        array $children,
        string $childKey,
        array &$visited,
    ): array {
        $visited[$id] = true;

        $node = $indexed[$id];
        unset($node[$childKey]);

        if (isset($children[$id])) {
            $sub = [];
            foreach ($children[$id] as $childId) {
                // 环状引用会重复挂载，已挂过的节点直接跳过
                if (isset($visited[$childId])) {
                    continue;
                }
                $sub[] = self::attach($childId, $indexed, $children, $childKey, $visited);
            }
            $node[$childKey] = $sub;
        }

        return $node;
    }

    /**
     * 前序展开节点。
     *
     * @param array<array-key, mixed> $nodes 节点集合
     * @param string $childKey 子级字段名
     * @param array<int, array<array-key, mixed>> $list 收集结果
     */
    private static function flatten(array $nodes, string $childKey, array &$list): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) {
                throw new InvalidArgumentException('树节点必须是数组');
            }

            $children = $node[$childKey] ?? [];
            unset($node[$childKey]);
            $list[] = $node;

            if (!is_array($children)) {
                throw new InvalidArgumentException('子级必须是数组');
            }

            self::flatten($children, $childKey, $list);
        }
    }
}
