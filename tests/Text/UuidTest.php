<?php

declare(strict_types=1);

namespace Fxri\Tests\Text;

use Fxri\Text\Uuid;
use Fxri\Text\UuidMode;
use Fxri\Text\UuidStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 标识串生成测试：覆盖形态与二次加工样式。
 */
final class UuidTest extends TestCase
{
    /**
     * 标准形态为 8-4-4-4-12 的小写十六进制串。
     */
    public function testStandard(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            Uuid::make(),
        );
    }

    /**
     * 相同种子产出相同结果。
     */
    public function testSeedIsDeterministic(): void
    {
        self::assertSame(strtoupper(md5('fxri')), Uuid::make(UuidMode::Md5Upper, 'fxri'));
    }

    /**
     * 大写形态为无横线的 32 位串。
     */
    public function testUpper32(): void
    {
        self::assertMatchesRegularExpression('/^[0-9A-F]{32}$/', Uuid::make(UuidMode::Upper32));
    }

    /**
     * 各样式按约定产出 32 位或中间 16 位。
     *
     * @param UuidStyle $style 加工样式
     * @param string $pattern 期望字符集与长度
     * @param int $length 期望字符数
     */
    #[DataProvider('styleProvider')]
    public function testStyle(UuidStyle $style, string $pattern, int $length): void
    {
        $uuid = Uuid::make(UuidMode::Standard, null, $style);

        self::assertMatchesRegularExpression($pattern, $uuid);
        self::assertSame($length, strlen($uuid));
    }

    /**
     * 四种加工样式的期望形态。
     *
     * @return array<string, array{UuidStyle, string, int}> 用例集合
     */
    public static function styleProvider(): array
    {
        return [
            '全部小写' => [UuidStyle::Lower32, '/^[0-9a-f]{32}$/', 32],
            '全部大写' => [UuidStyle::Upper32, '/^[0-9A-F]{32}$/', 32],
            '中间小写' => [UuidStyle::Lower16, '/^[0-9a-f]{16}$/', 16],
            '中间大写' => [UuidStyle::Upper16, '/^[0-9A-F]{16}$/', 16],
        ];
    }
}
