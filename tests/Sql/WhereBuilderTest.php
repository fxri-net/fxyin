<?php

declare(strict_types=1);

namespace Fxri\Tests\Sql;

use Fxri\Sql\Boolean;
use Fxri\Sql\Collation;
use Fxri\Sql\Expression;
use Fxri\Sql\WhereBuilder;
use Fxri\Sql\WhereOperator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 条件构建器测试：逐算子覆盖片段与绑定，并验证值不会拼进语句。
 */
final class WhereBuilderTest extends TestCase
{
    /**
     * 单个算子生成预期的片段与绑定。
     *
     * @param WhereOperator $operator 比较方式
     * @param mixed $value 比较值
     * @param string $sql 期望片段
     * @param array<int, mixed> $bindings 期望绑定
     */
    #[DataProvider('operatorProvider')]
    public function testCompare(WhereOperator $operator, mixed $value, string $sql, array $bindings): void
    {
        $built = WhereBuilder::make()->compare($operator, 'name', $value)->build();

        self::assertSame($sql, $built->sql());
        self::assertSame($bindings, $built->bindings());
    }

    /**
     * 算子与期望片段的对照表。
     *
     * @return array<string, array{WhereOperator, mixed, string, array<int, mixed>}> 用例集合
     */
    public static function operatorProvider(): array
    {
        return [
            '等于' => [WhereOperator::Eq, 'fx', '`name` = ?', ['fx']],
            '不等于' => [WhereOperator::Ne, 'fx', '`name` <> ?', ['fx']],
            '大于' => [WhereOperator::Gt, 1, '`name` > ?', [1]],
            '大于等于' => [WhereOperator::Gte, 1, '`name` >= ?', [1]],
            '小于' => [WhereOperator::Lt, 1, '`name` < ?', [1]],
            '小于等于' => [WhereOperator::Lte, 1, '`name` <= ?', [1]],
            '模糊匹配' => [WhereOperator::Like, '%fx%', '`name` LIKE ?', ['%fx%']],
            '集合包含' => [WhereOperator::In, ['a', 'b'], '`name` IN (?, ?)', ['a', 'b']],
            '不为空' => [WhereOperator::NotNull, null, '`name` IS NOT NULL', []],
            '为空' => [WhereOperator::Null, null, '`name` IS NULL', []],
            '区间' => [WhereOperator::Between, [1, 9], '`name` BETWEEN ? AND ?', [1, 9]],
        ];
    }

    /**
     * 大小写策略只影响字符串比较。
     */
    public function testCollation(): void
    {
        $sensitive = WhereBuilder::make()->eq('name', 'Fx', Collation::CaseSensitive)->build();
        $numeric = WhereBuilder::make()->gt('age', 18, Collation::CaseSensitive)->build();

        self::assertSame('`name` = BINARY ?', $sensitive->sql());
        self::assertSame('`age` > ?', $numeric->sql());
    }

    /**
     * 连接方式与取反只作用于紧随其后的条件。
     */
    public function testBooleanAndNegate(): void
    {
        $built = WhereBuilder::make()
            ->eq('a', 1)
            ->or()
            ->not()
            ->eq('b', 2)
            ->and()
            ->isNull('c')
            ->build();

        self::assertSame('`a` = ? OR NOT (`b` = ?) AND `c` IS NULL', $built->sql());
        self::assertSame([1, 2], $built->bindings());
    }

    /**
     * 分组整体加括号，并按指定连接方式拼接。
     */
    public function testGroup(): void
    {
        $built = WhereBuilder::make()
            ->eq('a', 1)
            ->group(static function (WhereBuilder $builder): void {
                $builder->eq('b', 2)->or()->eq('c', 3);
            }, Boolean::Or)
            ->build();

        self::assertSame('`a` = ? OR (`b` = ? OR `c` = ?)', $built->sql());
        self::assertSame([1, 2, 3], $built->bindings());
    }

    /**
     * 嵌套分组沿用当前连接方式。
     */
    public function testNestedFollowsCurrentBoolean(): void
    {
        $built = WhereBuilder::make()
            ->eq('a', 1)
            ->or()
            ->nested(static function (WhereBuilder $builder): void {
                $builder->eq('b', 2)->eq('c', 3);
            })
            ->build();

        self::assertSame('`a` = ? OR (`b` = ? AND `c` = ?)', $built->sql());
        self::assertSame([1, 2, 3], $built->bindings());
    }

    /**
     * 空分组被忽略，不影响后续条件。
     */
    public function testEmptyGroupIgnored(): void
    {
        $built = WhereBuilder::make()
            ->or()
            ->group(static function (WhereBuilder $builder): void {
            })
            ->eq('a', 1)
            ->build();

        self::assertSame('`a` = ?', $built->sql());
    }

    /**
     * 空集合条件恒为假，且不产生绑定。
     */
    public function testEmptyInIsAlwaysFalse(): void
    {
        $built = WhereBuilder::make()->in('a', [])->build();

        self::assertSame('0 = 1', $built->sql());
        self::assertSame([], $built->bindings());
    }

    /**
     * 区间条件必须恰好两个边界值。
     */
    public function testBetweenRequiresTwoBounds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        WhereBuilder::make()->between('a', [1]);
    }

    /**
     * 注入串只出现在绑定里，不进入语句。
     */
    public function testValueStaysInBindings(): void
    {
        $payload = ' OR 1=1 -- ';
        $built = WhereBuilder::make()->eq('name', $payload)->build();

        self::assertStringNotContainsString($payload, $built->sql());
        self::assertSame([$payload], $built->bindings());
    }

    /**
     * 原生表达式不做字段包装。
     */
    public function testExpressionColumn(): void
    {
        $built = WhereBuilder::make()->eq(Expression::make('COUNT(*)'), 1)->build();

        self::assertSame('COUNT(*) = ?', $built->sql());
    }

    /**
     * 无条件时片段为空。
     */
    public function testBuildEmpty(): void
    {
        self::assertTrue(WhereBuilder::make()->build()->isEmpty());
    }
}
