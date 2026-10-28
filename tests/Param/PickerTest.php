<?php

declare(strict_types=1);

namespace Fxri\Tests\Param;

use Fxri\Param\Picker;
use PHPUnit\Framework\TestCase;

/**
 * 取值工具测试：覆盖只保留、按结构提取与点分取值。
 */
final class PickerTest extends TestCase
{
    /**
     * 按结构提取时缺失键补 null，数组值递归下钻。
     */
    public function testPick(): void
    {
        $source = ['id' => 7, 'user' => ['name' => '风音', 'tags' => ['a']]];

        self::assertSame(
            [
                'id' => 7,
                'missing' => null,
                'user' => ['name' => '风音', 'email' => null],
                'list' => ['x' => null],
            ],
            Picker::pick($source, ['id', 'missing', 'user' => ['name', 'email'], 'list' => ['x']]),
        );
    }

    /**
     * 只保留已存在的键。
     */
    public function testOnly(): void
    {
        self::assertSame(['a' => 1], Picker::only(['a' => 1, 'b' => 2], ['a', 'c']));
    }

    /**
     * 点分路径取值，缺失时返回默认值。
     */
    public function testPluck(): void
    {
        self::assertSame('风音', Picker::pluck(['user' => ['name' => '风音']], 'user.name'));
        self::assertSame('fallback', Picker::pluck(['user' => []], 'user.name', 'fallback'));
    }
}
