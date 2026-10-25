<?php

declare(strict_types=1);

namespace Fxri\Tests\Param;

use Fxri\Param\Shape;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 定形规则测试：逐条覆盖旧实现的参数定义模式。
 */
final class ShapeTest extends TestCase
{
    /**
     * 每种旧模式都能用链式组合等价表达。
     *
     * @param Shape $shape 定形规则
     * @param array<array-key, mixed> $input 原始输入
     * @param array<array-key, mixed> $defaults 键与默认值
     * @param array<array-key, mixed> $expected 期望结果
     */
    #[DataProvider('shapeProvider')]
    public function testApply(Shape $shape, array $input, array $defaults, array $expected): void
    {
        self::assertSame($expected, $shape->apply($input, $defaults));
    }

    /**
     * 旧模式与新链式组合的对照表。
     *
     * @return array<string, array{Shape, array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>}> 用例集合
     */
    public static function shapeProvider(): array
    {
        return [
            '1.1.1 保留输入，缺失补默认值' => [
                Shape::merge(),
                ['name' => '', 'extra' => 'keep'],
                ['name' => 'anon', 'age' => 0],
                ['name' => '', 'extra' => 'keep', 'age' => 0],
            ],
            '1.1.2 保留输入，缺失或空串补默认值' => [
                Shape::merge()->fillWhenBlank(),
                ['name' => '', 'extra' => 'keep'],
                ['name' => 'anon'],
                ['name' => 'anon', 'extra' => 'keep'],
            ],
            '1.1.3 保留输入，缺失补默认值后解码 JSON' => [
                Shape::merge()->fillWhenAbsent()->json(),
                ['meta' => '{"a":1}'],
                ['meta' => '{}', 'other' => null],
                ['meta' => ['a' => 1], 'other' => []],
            ],
            '1.2.1 保留输入，缺失置空' => [
                Shape::merge()->nullWhenAbsent(),
                ['name' => 'fx'],
                ['name' => null, 'note' => null],
                ['name' => 'fx', 'note' => null],
            ],
            '1.2.2 保留输入，缺失或空串置空' => [
                Shape::merge()->nullWhenBlank(),
                ['name' => ''],
                ['name' => 'anon'],
                ['name' => null],
            ],
            '1.2.3 保留输入，缺失置空后解码 JSON' => [
                Shape::merge()->nullWhenAbsent()->json(),
                ['meta' => '{"a":1}'],
                ['meta' => null, 'other' => null],
                ['meta' => ['a' => 1], 'other' => []],
            ],
            '1.2.4 保留输入，缺失或空串置空后拆逗号' => [
                Shape::merge()->nullWhenBlank()->csv(),
                ['tags' => 'x,y'],
                ['tags' => null, 'none' => null],
                ['tags' => ['x', 'y'], 'none' => []],
            ],
            '1.3.1 默认值优先于输入' => [
                Shape::merge()->defaultsFirst(),
                ['name' => 'fx'],
                ['name' => 'anon', 'note' => null],
                ['name' => 'anon', 'note' => null],
            ],
            '1.3.2 默认值优先，缺失置空' => [
                Shape::merge()->defaultsFirst()->nullWhenAbsent(),
                ['name' => ''],
                ['name' => 'anon', 'note' => null],
                ['name' => 'anon', 'note' => null],
            ],
            '2.1.1 只取声明键，缺失补默认值' => [
                Shape::only(),
                ['name' => 'fx', 'extra' => 1],
                ['name' => 'anon', 'age' => 0],
                ['name' => 'fx', 'age' => 0],
            ],
            '2.1.2 只取声明键，缺失或空串补默认值' => [
                Shape::only()->fillWhenBlank(),
                ['name' => '', 'extra' => 1],
                ['name' => 'anon'],
                ['name' => 'anon'],
            ],
            '2.2.1 只取声明键，缺失置空' => [
                Shape::only()->nullWhenAbsent(),
                ['extra' => 1],
                ['name' => null, 'age' => null],
                ['name' => null, 'age' => null],
            ],
            '2.2.2 只取声明键，缺失或空串置空' => [
                Shape::only()->nullWhenBlank(),
                ['name' => '', 'age' => 18],
                ['name' => 'anon', 'age' => null],
                ['name' => null, 'age' => 18],
            ],
        ];
    }
}
