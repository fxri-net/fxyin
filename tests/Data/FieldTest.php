<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Field;
use Fxri\Data\FieldJsonMode;
use Fxri\Data\FieldTextMode;
use Fxri\Sql\Expression;
use Fxri\Text\DateGranularity;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 字段表达式测试：SQL 与绑定分离，各枚举取值全覆盖。
 */
final class FieldTest extends TestCase
{
    /**
     * 默认模式给路径加双引号，值与兜底值全部走绑定。
     */
    public function testJsonQuoted(): void
    {
        $built = Field::make()->json('data', 'info.name');

        self::assertSame(
            'IF(JSON_VALID(`data`), IFNULL(JSON_UNQUOTE(JSON_EXTRACT(`data`, ?)), ?), ?)',
            $built->sql(),
        );
        self::assertSame(['$."info"."name"', '', ''], $built->bindings());
    }

    /**
     * 原始模式不加工路径。
     */
    public function testJsonRaw(): void
    {
        $built = Field::make()->json('data', '$.info.name', FieldJsonMode::Raw, '无');

        self::assertSame(['$.info.name', '无', '无'], $built->bindings());
    }

    /**
     * 带根符号的路径同样规范化。
     */
    public function testJsonPathWithRoot(): void
    {
        $built = Field::make()->json('data', '$.info.name');

        self::assertSame('$."info"."name"', $built->bindings()[0]);
    }

    /**
     * 路径不能为空。
     */
    public function testJsonRejectsEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Field::make()->json('data', ' ');
    }

    /**
     * 文本片段按模式与兜底值类型变化。
     *
     * @param mixed $replace 兜底值
     * @param FieldTextMode $mode 文本模式
     * @param string $expected 预期语句
     */
    #[DataProvider('textProvider')]
    public function testText(mixed $replace, FieldTextMode $mode, string $expected): void
    {
        $built = Field::make()->text('name', $replace, $mode);

        self::assertSame($expected, $built->sql());
        self::assertSame([$replace], $built->bindings());
    }

    /**
     * 文本模式数据提供者。
     *
     * @return array<string, array{0: mixed, 1: FieldTextMode, 2: string}> 用例集合
     */
    public static function textProvider(): array
    {
        return [
            '字符串兜底' => ['', FieldTextMode::Keep, 'IF(LENGTH(`name`) = 0 OR `name` IS NULL, ?, `name`)'],
            '整数兜底转整数' => [0, FieldTextMode::Keep, 'CONVERT(IF(LENGTH(`name`) = 0 OR `name` IS NULL, ?, `name`), SIGNED)'],
            '只替换空值' => [0, FieldTextMode::ReplaceEmpty, 'IF(LENGTH(`name`) = 0 OR `name` IS NULL, ?, `name`)'],
            '强制转整数' => ['x', FieldTextMode::CastInt, 'CONVERT(IF(LENGTH(`name`) = 0 OR `name` IS NULL, ?, `name`), SIGNED)'],
        ];
    }

    /**
     * 粒度决定输出格式。
     *
     * @param DateGranularity $granularity 时间粒度
     * @param string $format 预期格式
     */
    #[DataProvider('dateProvider')]
    public function testDate(DateGranularity $granularity, string $format): void
    {
        $built = Field::make()->date('created_at', $granularity);

        self::assertSame('FROM_UNIXTIME(`created_at`, ?)', $built->sql());
        self::assertSame([$format], $built->bindings());
    }

    /**
     * 时间粒度数据提供者。
     *
     * @return array<string, array{0: DateGranularity, 1: string}> 用例集合
     */
    public static function dateProvider(): array
    {
        return [
            '日期与时间' => [DateGranularity::DateTime, '%Y-%m-%d %H:%i:%s'],
            '仅日期' => [DateGranularity::Date, '%Y-%m-%d'],
            '仅时间' => [DateGranularity::Time, '%H:%i:%s'],
        ];
    }

    /**
     * 传兜底值时套一层空值判断。
     */
    public function testDateWithReplace(): void
    {
        $built = Field::make()->date('created_at', DateGranularity::Date, 0);

        self::assertSame(
            'IF(`created_at` = 0 OR `created_at` IS NULL, ?, FROM_UNIXTIME(`created_at`, ?))',
            $built->sql(),
        );
        self::assertSame([0, '%Y-%m-%d'], $built->bindings());
    }

    /**
     * 表达式字段原样使用，不再加反引号。
     */
    public function testExpressionColumn(): void
    {
        $built = Field::make()->date(Expression::make('FROM_UNIXTIME(1)'), DateGranularity::Time);

        self::assertSame('FROM_UNIXTIME(FROM_UNIXTIME(1), ?)', $built->sql());
    }
}
