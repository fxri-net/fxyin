<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Cast;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 数组与对象转换测试：空结构区分与递归深度。
 */
final class CastTest extends TestCase
{
    /**
     * 对象按属性展开，嵌套数组递归转换。
     */
    public function testToArray(): void
    {
        $object = new stdClass();
        $object->name = '风音';
        $object->nested = (object) ['id' => 1];

        self::assertSame(['name' => '风音', 'nested' => ['id' => 1]], Cast::toArray($object));
        self::assertSame('风音', Cast::toArray('风音'));
    }

    /**
     * 空数组转为空对象，保证 JSON 编码为 `{}`。
     */
    public function testToObject(): void
    {
        $converted = Cast::toObject(['empty' => [], 'name' => '风音', 'nested' => ['id' => 1]]);

        self::assertInstanceOf(stdClass::class, $converted);
        self::assertInstanceOf(stdClass::class, $converted->empty);
        self::assertSame('风音', $converted->name);
        self::assertSame('{}', json_encode($converted->empty));
        self::assertInstanceOf(stdClass::class, $converted->nested);
    }

    /**
     * 只把空数组转为对象，非空数组与列表保持数组。
     */
    public function testEmptyToObject(): void
    {
        $converted = Cast::emptyToObject(['empty' => [], 'list' => [1, 2], 'nested' => ['deep' => []]]);

        self::assertIsArray($converted);
        self::assertInstanceOf(stdClass::class, $converted['empty']);
        self::assertSame([1, 2], $converted['list']);
        self::assertIsArray($converted['nested']);
        self::assertInstanceOf(stdClass::class, $converted['nested']['deep']);
        self::assertSame(['empty' => []], Cast::emptyToObject(['empty' => []], 0));
    }

    /**
     * 深度为 0 时不处理，深度为 1 时只处理一层。
     */
    public function testLimit(): void
    {
        $value = ['nested' => []];

        self::assertSame($value, Cast::toObject($value, 0));
        $limited = Cast::toObject($value, 1);
        self::assertInstanceOf(stdClass::class, $limited);
        self::assertSame([], $limited->nested);
    }
}
