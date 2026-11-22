<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Data\Page;
use Fxri\Http\Envelope;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 响应信封测试：五键结构、成功判断与分页数据转换。
 */
final class EnvelopeTest extends TestCase
{
    /**
     * 成功信封默认业务码为 200，输出固定五键。
     */
    public function testSuccess(): void
    {
        $envelope = Envelope::success(['id' => 1], '完成', 200, ['trace' => 'abc']);

        self::assertTrue($envelope->isSuccess());
        self::assertSame(200, $envelope->code());
        self::assertSame('完成', $envelope->message());
        self::assertSame(['id' => 1], $envelope->data());
        self::assertSame(['trace' => 'abc'], $envelope->extend());
        self::assertSame([], $envelope->debug());

        $array = $envelope->toArray();
        self::assertSame(['code', 'message', 'data', 'extend', 'debug'], array_keys($array));
        self::assertSame(200, $array['code']);
        self::assertSame('完成', $array['message']);
        self::assertSame(['id' => 1], $array['data']);
        // 扩展参数在输出侧恒为对象，PHP 内部取值接口仍返回数组
        self::assertInstanceOf(stdClass::class, $array['extend']);
        self::assertSame(['trace' => 'abc'], (array) $array['extend']);
        self::assertInstanceOf(stdClass::class, $array['debug']);
    }

    /**
     * 两个扩展位恒为具名映射：空值输出空对象、关联数组原样输出、列表归入保留键 items。
     */
    public function testExtensionMap(): void
    {
        $empty = Envelope::success()->toArray();
        self::assertInstanceOf(stdClass::class, $empty['extend']);
        self::assertInstanceOf(stdClass::class, $empty['debug']);
        self::assertSame([], (array) $empty['extend']);
        self::assertSame([], (array) $empty['debug']);

        $assoc = Envelope::success(null, '', 200, ['trace' => 'abc'], ['time' => 12])->toArray();
        self::assertSame(['trace' => 'abc'], (array) $assoc['extend']);
        self::assertSame(['time' => 12], (array) $assoc['debug']);

        $list = Envelope::success(null, '', 200, ['a', 'b'], ['x', 'y'])->toArray();
        self::assertInstanceOf(stdClass::class, $list['extend']);
        self::assertInstanceOf(stdClass::class, $list['debug']);
        self::assertSame(['items' => ['a', 'b']], (array) $list['extend']);
        self::assertSame(['items' => ['x', 'y']], (array) $list['debug']);
    }

    /**
     * 失败信封保留整数业务码，并给出调试信息。
     */
    public function testFailure(): void
    {
        $envelope = Envelope::failure(1001, '参数错误', null, ['error' => 'name'], ['time' => 12]);

        self::assertFalse($envelope->isSuccess());
        self::assertSame(1001, $envelope->code());
        self::assertSame('参数错误', $envelope->message());
        self::assertSame(['time' => 12], $envelope->debug());
        // 输出侧恒为对象，取值接口仍返回数组
        self::assertInstanceOf(stdClass::class, $envelope->toArray()['debug']);
        self::assertSame(['time' => 12], (array) $envelope->toArray()['debug']);
    }

    /**
     * 分页数据在输出时展开为四键数组。
     */
    public function testPageData(): void
    {
        $page = Page::make([['id' => 1]], 9, 2, 5);
        $envelope = Envelope::success($page);

        self::assertSame($page, $envelope->data());
        self::assertSame(
            ['total' => 9, 'records' => [['id' => 1]], 'page' => 2, 'size' => 5],
            $envelope->toArray()['data'],
        );
    }
}
