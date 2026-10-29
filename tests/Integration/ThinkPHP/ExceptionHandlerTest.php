<?php

declare(strict_types=1);

namespace Fxri\Tests\Integration\ThinkPHP;

use Fxri\Exception\SameDataException;
use Fxri\Exception\ValidationException;
use Fxri\Integration\ThinkPHP\ExceptionHandler;
use Fxri\KernelBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;

/**
 * 异常响应适配测试：三类异常映射、日志留痕与响应输出。
 */
final class ExceptionHandlerTest extends TestCase
{
    /**
     * 校验异常映射为参数错误码并保留原文。
     */
    public function testValidationEnvelope(): void
    {
        $envelope = ExceptionHandler::make(KernelBuilder::make()->build())
            ->envelope(new ValidationException('手机号格式不正确'));

        self::assertFalse($envelope->isSuccess());
        self::assertSame(ExceptionHandler::INVALID, $envelope->code());
        self::assertSame('手机号格式不正确', $envelope->message());
    }

    /**
     * 数据未变更异常映射为专用码并保留原文。
     */
    public function testSameDataEnvelope(): void
    {
        $envelope = ExceptionHandler::make(KernelBuilder::make()->build())
            ->envelope(new SameDataException('没有需要保存的变更'));

        self::assertFalse($envelope->isSuccess());
        self::assertSame(ExceptionHandler::UNCHANGED, $envelope->code());
        self::assertSame('没有需要保存的变更', $envelope->message());
    }

    /**
     * 其余异常返回内部错误并写入日志，不暴露原始信息。
     */
    public function testInternalEnvelope(): void
    {
        $logger = self::logger();
        $kernel = KernelBuilder::make()->withLogger($logger)->build();

        $envelope = ExceptionHandler::make($kernel)->envelope(new RuntimeException('数据库连接失败'));

        self::assertSame(ExceptionHandler::INTERNAL, $envelope->code());
        self::assertSame('服务内部错误', $envelope->message());
        self::assertSame([['level' => 'error', 'message' => '数据库连接失败']], $logger->records);
    }

    /**
     * 输出响应体为固定结构。
     */
    public function testRespond(): void
    {
        ob_start();
        $body = ExceptionHandler::make(KernelBuilder::make()->build())
            ->respond(new ValidationException('参数不合法'));
        ob_end_clean();

        self::assertSame('{"code":422,"message":"参数不合法","data":null,"extend":[],"debug":{}}', $body);
    }

    /**
     * 构造记录型日志。
     *
     * @return object 同时暴露 records 的日志实例
     */
    private static function logger(): object
    {
        return new class () extends AbstractLogger {
            /** @var list<array{level: string, message: string}> 日志记录 */
            public array $records = [];

            /**
             * 记录日志。
             *
             * @param mixed $level 级别
             * @param string|\Stringable $message 消息
             * @param array<array-key, mixed> $context 上下文
             * @return void
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => (string) $level, 'message' => (string) $message];
            }
        };
    }
}
