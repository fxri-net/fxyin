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
        self::assertSame(['trace' => 'abc'], $array['extend']);
        self::assertInstanceOf(stdClass::class, $array['debug']);
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
        self::assertSame(['time' => 12], $envelope->toArray()['debug']);
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
