<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * 响应输出。
 *
 * 负责把信封交给格式化器并落到底层输出；格式化策略可整体替换。
 */
final class Responder
{
    /**
     * 初始化。
     *
     * @param Formatter $formatter 格式化器
     */
    private function __construct(private readonly Formatter $formatter)
    {
    }

    /**
     * 取响应内容类型。
     *
     * @return string 内容类型
     */
    public function contentType(): string
    {
        return $this->formatter->contentType();
    }

    /**
     * 格式化响应信封。
     *
     * @param Envelope $envelope 响应信封
     * @return string 响应体
     */
    public function format(Envelope $envelope): string
    {
        return $this->formatter->format($envelope);
    }

    /**
     * 创建实例。
     *
     * @param Formatter|null $formatter 格式化器，缺省为 JSON
     * @return self 响应输出
     */
    public static function make(?Formatter $formatter = null): self
    {
        return new self($formatter ?? JsonFormatter::make());
    }

    /**
     * 输出响应。
     *
     * 只设置内容类型，HTTP 状态码由接入方决定；已发出响应头时只写正文。
     *
     * @param Envelope $envelope 响应信封
     * @return string 已输出的响应体
     */
    public function send(Envelope $envelope): string
    {
        $body = $this->format($envelope);

        if (!headers_sent()) {
            header('Content-Type: ' . $this->contentType());
        }

        echo $body;

        return $body;
    }

    /**
     * 替换格式化器。
     *
     * @param Formatter $formatter 格式化器
     * @return self 新的响应输出
     */
    public function withFormatter(Formatter $formatter): self
    {
        return new self($formatter);
    }
}
