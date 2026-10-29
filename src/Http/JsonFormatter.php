<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Param\Json;

/**
 * JSON 响应格式化器。
 *
 * 默认实现，输出固定五字段的 JSON 对象。
 */
final class JsonFormatter implements Formatter
{
    /**
     * 初始化。
     *
     * @param int $flags 编码选项
     */
    private function __construct(private readonly int $flags)
    {
    }

    /**
     * 取响应内容类型。
     *
     * @return string 内容类型
     */
    public function contentType(): string
    {
        return 'application/json; charset=utf-8';
    }

    /**
     * 格式化响应信封。
     *
     * @param Envelope $envelope 响应信封
     * @return string JSON 串
     */
    public function format(Envelope $envelope): string
    {
        return Json::encode($envelope->toArray(), $this->flags);
    }

    /**
     * 创建实例。
     *
     * @param int $flags 编码选项
     * @return self 格式化器
     */
    public static function make(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): self
    {
        return new self($flags);
    }
}
