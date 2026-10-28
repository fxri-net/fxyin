<?php

declare(strict_types=1);

namespace Fxri\Tests\Param;

use Fxri\Param\Merger;
use PHPUnit\Framework\TestCase;

/**
 * 数组合并测试：覆盖追加与递归合并。
 */
final class MergerTest extends TestCase
{
    /**
     * 字符串键覆盖，数字键追加。
     */
    public function testAppend(): void
    {
        self::assertSame(
            ['a' => 2, 0 => 'x', 1 => 'y'],
            Merger::append(['a' => 1], ['x'], ['a' => 2, 'y']),
        );
    }

    /**
     * 递归合并时覆盖层优先。
     */
    public function testMerge(): void
    {
        self::assertSame(
            ['a' => ['b' => 2, 'c' => 3]],
            Merger::merge(['a' => ['b' => 1, 'c' => 2]], ['a' => ['b' => 2, 'c' => 3]]),
        );
    }
}
