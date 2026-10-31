<?php

declare(strict_types=1);

namespace Fxri\Http\Cookie;

/**
 * Cookie 选项。
 *
 * 把 setcookie 的一串位置参数收敛成对象，调用处不必记忆参数顺序。
 */
final class CookieOptions
{
    /** 默认生效路径 */
    private const DEFAULT_PATH = '/';

    /** 默认跨站策略 */
    private const DEFAULT_SAME_SITE = SameSite::Lax;

    /**
     * 初始化。
     *
     * @param int $expires 过期时间戳，0 表示会话级
     * @param string $path 生效路径
     * @param string $domain 生效域
     * @param bool $secure 仅 HTTPS 传输
     * @param bool $httpOnly 禁止脚本读取
     * @param SameSite $sameSite 跨站策略
     */
    private function __construct(
        public readonly int $expires = 0,
        public readonly string $path = self::DEFAULT_PATH,
        public readonly string $domain = '',
        public readonly bool $secure = false,
        public readonly bool $httpOnly = true,
        public readonly SameSite $sameSite = self::DEFAULT_SAME_SITE,
    ) {
    }

    /**
     * 创建实例。
     *
     * @param int $expires 过期时间戳，0 表示会话级
     * @param string $path 生效路径
     * @param string $domain 生效域
     * @param bool $secure 仅 HTTPS 传输
     * @param bool $httpOnly 禁止脚本读取
     * @param SameSite $sameSite 跨站策略
     * @return self 选项
     */
    public static function make(
        int $expires = 0,
        string $path = self::DEFAULT_PATH,
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        SameSite $sameSite = self::DEFAULT_SAME_SITE,
    ): self {
        return new self($expires, $path, $domain, $secure, $httpOnly, $sameSite);
    }

    /**
     * 转为 setcookie 选项数组。
     *
     * 始终输出 expires、path、httponly、samesite；domain 非空、secure 开启时才追加，避免下发空值与无用开关。
     *
     * @return array{expires: int, path: string, httponly: bool, samesite: 'Strict'|'Lax'|'None', domain?: string, secure?: bool} 选项数组
     */
    public function toArray(): array
    {
        /** @var array{expires: int, path: string, httponly: bool, samesite: 'Strict'|'Lax'|'None', domain?: string, secure?: bool} $options */
        $options = [
            'expires' => $this->expires,
            'path' => $this->path,
            'httponly' => $this->httpOnly,
            // 展开成字面量，setcookie 只接受固定的几个取值
            'samesite' => match ($this->sameSite) {
                SameSite::Strict => 'Strict',
                SameSite::Lax => 'Lax',
                SameSite::None => 'None',
            },
        ];

        if ('' !== $this->domain) {
            $options['domain'] = $this->domain;
        }

        if ($this->secure) {
            $options['secure'] = true;
        }

        return $options;
    }
}
