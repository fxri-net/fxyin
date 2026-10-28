<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Hierarchy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 层级关系测试：祖先、后代、自引用与环状关系。
 */
final class HierarchyTest extends TestCase
{
    /**
     * 祖先由近及远，直到根节点。
     */
    public function testAncestors(): void
    {
        self::assertSame([3, 2, 1], Hierarchy::ancestors($this->rows(), 4));
        self::assertSame([], Hierarchy::ancestors($this->rows(), 1));
        self::assertSame([], Hierarchy::ancestors($this->rows(), 99));
    }

    /**
     * 后代按层级展开，环状关系只记录一次。
     */
    public function testDescendants(): void
    {
        self::assertSame([2, 5, 3, 6, 4], Hierarchy::descendants($this->rows(), 1));
        self::assertSame([], Hierarchy::descendants($this->rows(), 4));
        self::assertSame([6], Hierarchy::descendants($this->rows(), 5));
    }

    /**
     * 缺少主键的列表项直接拒绝。
     */
    public function testMissingPrimaryKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('列表项缺少主键');

        Hierarchy::ancestors([['parent_id' => 0]], 1);
    }

    /**
     * 测试数据。
     *
     * @return array<int, array<string, int>> 扁平列表
     */
    private function rows(): array
    {
        return [
            ['id' => 1, 'parent_id' => 0],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 2],
            ['id' => 4, 'parent_id' => 3],
            ['id' => 5, 'parent_id' => 1],
            ['id' => 6, 'parent_id' => 5],
        ];
    }
}
