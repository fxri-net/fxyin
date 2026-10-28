<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\NullPolicy;
use Fxri\Data\Param;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 参数规范化测试：去空与缺键检查。
 */
final class ParamTest extends TestCase
{
    /**
     * 只去掉 null，保留 0、空串与 false。
     */
    public function testFilter(): void
    {
        self::assertSame(
            ['a' => 1, 'b' => 0, 'c' => ''],
            Param::filter(['a' => 1, 'b' => 0, 'c' => '', 'd' => null]),
        );
    }

    /**
     * 按策略列出缺失键。
     *
     * @param NullPolicy $policy 空值判定策略
     * @param array<int, string> $expected 预期缺失键
     */
    #[DataProvider('missingProvider')]
    public function testMissing(NullPolicy $policy, array $expected): void
    {
        $data = ['a' => 1, 'b' => '', 'c' => null, 'd' => 0];

        self::assertSame($expected, Param::missing($data, $policy));
    }

    /**
     * 空值策略数据提供者。
     *
     * @return array<string, array{0: NullPolicy, 1: array<int, string>}> 用例集合
     */
    public static function missingProvider(): array
    {
        return [
            '空串算空' => [NullPolicy::Blank, ['b', 'c']],
            '仅 null 算空' => [NullPolicy::StrictNull, ['c']],
        ];
    }

    /**
     * 默认策略与 Blank 一致。
     */
    public function testMissingDefaultPolicy(): void
    {
        self::assertSame(['b'], Param::missing(['a' => 1, 'b' => '']));
    }

    /**
     * 至少一个非空值即视为存在。
     */
    public function testExist(): void
    {
        self::assertTrue(Param::exist(['a' => '', 'b' => 1]));
        self::assertFalse(Param::exist(['a' => '', 'b' => null]));
        self::assertTrue(Param::exist(['a' => null, 'b' => 0]));
    }

    /**
     * 多行数据补全缺失键并按主键排序。
     */
    public function testRepair(): void
    {
        $repaired = Param::repair([
            ['b' => 2, 'a' => 1],
            ['a' => 3],
        ]);

        self::assertSame([
            ['a' => 1, 'b' => 2],
            ['a' => 3, 'b' => ''],
        ], $repaired);
    }
}
