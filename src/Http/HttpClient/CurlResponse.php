<?php

declare(strict_types=1);

namespace Fxri\Http\HttpClient;

/**
 * cURL 响应快照。
 *
 * 只承载状态码、头与正文，不实现 PSR-7 接口，避免 URL 级请求引入消息包依赖。
 */
final class CurlResponse
{
    /**
     * 初始化。
     *
     * @param int $status 状态码
     * @param array<string, string> $headers 响应头，键为小写
     * @param string $body 响应正文
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    /**
     * 取响应正文。
     *
     * @return string 正文
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * 取单个响应头。
     *
     * @param string $name 头名，大小写不敏感
     * @param string $default 缺失时的默认值
     * @return string 头值
     */
    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower(trim($name))] ?? $default;
    }

    /**
     * 返回全部响应头。
     *
     * @return array<string, string> 头名小写 => 头值
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 判断是否 2xx。
     *
     * @return bool 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /**
     * 取状态码。
     *
     * @return int 状态码
     */
    public function status(): int
    {
        return $this->status;
    }
}
