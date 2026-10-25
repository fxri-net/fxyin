<?php

declare(strict_types=1);

namespace Fxri\Integration\ThinkPHP;

use Fxri\Exception\SameDataException;
use Fxri\Exception\ValidationException;
use Fxri\Http\Envelope;
use Fxri\Http\Formatter;
use Fxri\Http\Responder;
use Fxri\Kernel;
use Throwable;

/**
 * 异常响应适配。
 *
 * 把框架内两类可预期异常转成固定信封，其余异常记入日志后统一返回内部错误，
 * 避免把堆栈与实现细节暴露给调用方。
 */
final class ExceptionHandler
{
    /** 参数校验失败码 */
    public const INVALID = 'invalid';

    /** 数据未变更码 */
    public const UNCHANGED = 'unchanged';

    /** 内部错误码 */
    public const INTERNAL = 'internal';

    /**
     * 初始化。
     *
     * @param Kernel $kernel 内核
     * @param Responder $responder 响应输出
     */
    private function __construct(
        private readonly Kernel $kernel,
        private readonly Responder $responder,
    ) {
    }

    /**
     * 把异常转为响应信封。
     *
     * @param Throwable $error 异常
     * @return Envelope 响应信封
     */
    public function envelope(Throwable $error): Envelope
    {
        if ($error instanceof ValidationException) {
            return Envelope::failure(self::INVALID, $error->getMessage());
        }

        if ($error instanceof SameDataException) {
            return Envelope::failure(self::UNCHANGED, $error->getMessage());
        }

        $this->kernel->logger()->error($error->getMessage(), ['exception' => $error]);

        return Envelope::failure(self::INTERNAL, '服务内部错误');
    }

    /**
     * 创建实例。
     *
     * @param Kernel $kernel 内核
     * @param Formatter|null $formatter 格式化器，缺省为 JSON
     * @return self 异常响应适配
     */
    public static function make(Kernel $kernel, ?Formatter $formatter = null): self
    {
        return new self($kernel, Responder::make($formatter));
    }

    /**
     * 输出异常响应。
     *
     * @param Throwable $error 异常
     * @return string 已输出的响应体
     */
    public function respond(Throwable $error): string
    {
        return $this->responder->send($this->envelope($error));
    }
}
