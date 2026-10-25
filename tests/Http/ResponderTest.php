<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\Envelope;
use Fxri\Http\Formatter;
use Fxri\Http\Responder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 响应输出测试：覆盖默认 JSON、替换格式化器与信封校验。
 */
final class ResponderTest extends TestCase
{
    /**
     * 默认按 JSON 输出固定结构。
     */
    public function testDefaultJson(): void
    {
        $responder = Responder::make();

        self::assertSame('application/json; charset=utf-8', $responder->contentType());
        self::assertSame(
            '{"ok":true,"code":"ok","message":"完成","data":{"id":1}}',
            $responder->format(Envelope::success(['id' => 1], '完成')),
        );
    }

    /**
     * 替换格式化器后按新策略输出。
     */
    public function testCustomFormatter(): void
    {
        $responder = Responder::make(self::plainFormatter());

        self::assertSame('text/plain; charset=utf-8', $responder->contentType());
        self::assertSame('ok', $responder->format(Envelope::success()));
        self::assertSame('fail:1001', $responder->format(Envelope::failure('1001')));
    }

    /**
     * 输出返回响应体。
     */
    public function testSend(): void
    {
        ob_start();
        $body = Responder::make()->send(Envelope::failure('1001', '参数错误'));
        ob_end_clean();

        self::assertSame('{"ok":false,"code":"1001","message":"参数错误","data":null}', $body);
    }

    /**
     * 空业务码在构造期被拒绝。
     */
    public function testEmptyCodeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Envelope::failure('');
    }

    /**
     * 替换格式化器返回新实例。
     */
    public function testWithFormatter(): void
    {
        $responder = Responder::make()->withFormatter(self::plainFormatter());

        self::assertSame('text/plain; charset=utf-8', $responder->contentType());
    }

    /**
     * 构造纯文本格式化器。
     *
     * @return Formatter 格式化器
     */
    private static function plainFormatter(): Formatter
    {
        return new class () implements Formatter {
            public function contentType(): string
            {
                return 'text/plain; charset=utf-8';
            }

            public function format(Envelope $envelope): string
            {
                return $envelope->isOk() ? 'ok' : 'fail:' . $envelope->code();
            }
        };
    }
}
