<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Tree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 树形结构测试：建树、展平、往返一致与异常边界。
 */
final class TreeTest extends TestCase
{
    /**
     * 按父键把列表挂成树。
     */
    public function testFromList(): void
    {
        $tree = Tree::fromList($this->list());

        self::assertCount(2, $tree);
        self::assertSame('a-1', $tree[0]['_child'][0]['name']);
        self::assertSame('a-2', $tree[0]['_child'][1]['name']);
        self::assertArrayNotHasKey('_child', $tree[1]);
    }

    /**
     * 树转列表后与原始列表一致。
     */
    public function testRoundTrip(): void
    {
        self::assertSame($this->list(), Tree::toList(Tree::fromList($this->list())));
    }

    /**
     * 支持自定义主键、父键与子级键。
     */
    public function testCustomKeys(): void
    {
        $tree = Tree::fromList(
            [
                ['code' => 'a', 'pid' => 0, 'name' => 'a'],
                ['code' => 'b', 'pid' => 'a', 'name' => 'b'],
            ],
            0,
            'code',
            'pid',
            'children',
        );

        self::assertSame('b', $tree[0]['children'][0]['name']);
    }

    /**
     * 父级不存在的节点保留为根。
     */
    public function testOrphanBecomesRoot(): void
    {
        $tree = Tree::fromList([['id' => 1, 'parent_id' => 9, 'name' => 'x']]);

        self::assertCount(1, $tree);
        self::assertSame('x', $tree[0]['name']);
    }

    /**
     * 环状父子关系不会递归爆栈，节点也不会丢失。
     */
    public function testCycleKeepsAllNodes(): void
    {
        $list = [
            ['id' => 1, 'parent_id' => 2, 'name' => 'a'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'b'],
        ];
        $flat = Tree::toList(Tree::fromList($list));

        self::assertCount(2, $flat);
    }

    /**
     * 缺少主键的列表项直接报错。
     */
    public function testMissingId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tree::fromList([['name' => 'x']]);
    }

    /**
     * 非数组的列表项直接报错。
     */
    public function testInvalidItem(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tree::fromList(['x']);
    }

    /**
     * 构造前序排列的测试列表。
     *
     * @return array<int, array<array-key, mixed>> 扁平列表
     */
    private function list(): array
    {
        return [
            ['id' => 1, 'parent_id' => 0, 'name' => 'a'],
            ['id' => 2, 'parent_id' => 1, 'name' => 'a-1'],
            ['id' => 4, 'parent_id' => 2, 'name' => 'a-1-1'],
            ['id' => 3, 'parent_id' => 1, 'name' => 'a-2'],
            ['id' => 5, 'parent_id' => 0, 'name' => 'b'],
        ];
    }
}
