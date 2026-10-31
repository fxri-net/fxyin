<?php

declare(strict_types=1);

namespace Fxri\Http\HttpClient;

/**
 * cURL 传输选项。
 *
 * 用不可变值对象承载超时、重定向与证书校验，替代 1.x 的字符串键选项数组。
 */
final class CurlOptions
{
    /** 默认请求超时秒数 */
    private const DEFAULT_TIMEOUT = 30;

    /** 默认连接超时秒数 */
    private const DEFAULT_CONNECT_TIMEOUT = 10;

    /** 默认最大重定向次数 */
    private const DEFAULT_MAX_REDIRECTS = 5;

    /** 请求超时秒数 */
    public readonly int $timeout;

    /** 连接超时秒数 */
    public readonly int $connectTimeout;

    /** 是否跟随重定向 */
    public readonly bool $follow;

    /** 最大重定向次数 */
    public readonly int $maxRedirects;

    /** 是否校验证书与主机名 */
    public readonly bool $verify;

    /** 是否优先 IPv4 */
    public readonly bool $ipv4;

    /** 自定义 UA，null 表示不设置 */
    public readonly ?string $userAgent;

    /**
     * 初始化。
     *
     * @param int $timeout 请求超时秒数
     * @param int $connectTimeout 连接超时秒数
     * @param bool $follow 是否跟随重定向
     * @param int $maxRedirects 最大重定向次数
     * @param bool $verify 是否校验证书与主机名
     * @param bool $ipv4 是否优先 IPv4
     * @param string|null $userAgent 自定义 UA
     */
    public function __construct(
        int $timeout = self::DEFAULT_TIMEOUT,
        int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        bool $follow = true,
        int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
        bool $verify = true,
        bool $ipv4 = true,
        ?string $userAgent = null,
    ) {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
        $this->follow = $follow;
        $this->maxRedirects = $maxRedirects;
        $this->verify = $verify;
        $this->ipv4 = $ipv4;
        $this->userAgent = $userAgent;
    }

    /**
     * 创建选项。
     *
     * @param int $timeout 请求超时秒数
     * @param int $connectTimeout 连接超时秒数
     * @param bool $follow 是否跟随重定向
     * @param int $maxRedirects 最大重定向次数
     * @param bool $verify 是否校验证书与主机名
     * @param bool $ipv4 是否优先 IPv4
     * @param string|null $userAgent 自定义 UA
     * @return self 传输选项
     */
    public static function make(
        int $timeout = self::DEFAULT_TIMEOUT,
        int $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT,
        bool $follow = true,
        int $maxRedirects = self::DEFAULT_MAX_REDIRECTS,
        bool $verify = true,
        bool $ipv4 = true,
        ?string $userAgent = null,
    ): self {
        return new self($timeout, $connectTimeout, $follow, $maxRedirects, $verify, $ipv4, $userAgent);
    }
}
