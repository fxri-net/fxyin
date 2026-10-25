<?php

declare(strict_types=1);

namespace Fxri\Http\Cookie;

use Fxri\Support\Type;
use LogicException;

/**
 * Cookie 读写与签名。
 *
 * 维护一份当前请求的 Cookie 镜像，写入时同步更新，避免同请求内取到旧值。
 */
final class CookieJar
{
    /** 签名长度 */
    private const SIGNATURE_LENGTH = 64;

    /** @var array<string, string> Cookie 镜像 */
    private array $cookies;

    /** 签名密钥 */
    private readonly ?string $secret;

    /**
     * 初始化。
     *
     * @param array<array-key, mixed> $cookies Cookie 来源
     * @param string|null $secret 签名密钥，空串与 null 都视为未配置
     */
    private function __construct(array $cookies, ?string $secret)
    {
        $this->cookies = self::normalize($cookies);
        $this->secret = '' === $secret ? null : $secret;
    }

    /**
     * 返回全部 Cookie。
     *
     * @return array<string, string> Cookie 镜像
     */
    public function all(): array
    {
        return $this->cookies;
    }

    /**
     * 删除 Cookie。
     *
     * 复用原选项下发过期时间，否则同名不同路径的 Cookie 删不掉。
     *
     * @param string $name Cookie 名
     * @param CookieOptions|null $options 原选项，缺省为默认路径
     * @return bool 是否已下发
     */
    public function delete(string $name, ?CookieOptions $options = null): bool
    {
        $target = $options ?? CookieOptions::make();
        $expired = CookieOptions::make(
            time() - 3600,
            $target->path,
            $target->domain,
            $target->secure,
            $target->httpOnly,
            $target->sameSite,
        );

        if (!$this->set($name, '', $expired)) {
            return false;
        }

        unset($this->cookies[$name]);

        return true;
    }

    /**
     * 取当前请求的 Cookie。
     *
     * @param string|null $secret 签名密钥
     * @return self Cookie 容器
     */
    public static function fromGlobals(?string $secret = null): self
    {
        return new self($_COOKIE, $secret);
    }

    /**
     * 读取 Cookie。
     *
     * @param string $name Cookie 名
     * @param string $default 缺失时的默认值
     * @return string Cookie 值
     */
    public function get(string $name, string $default = ''): string
    {
        return $this->cookies[$name] ?? $default;
    }

    /**
     * 判断 Cookie 是否存在。
     *
     * @param string $name Cookie 名
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->cookies);
    }

    /**
     * 创建实例。
     *
     * @param array<array-key, mixed> $cookies Cookie 来源
     * @param string|null $secret 签名密钥
     * @return self Cookie 容器
     */
    public static function make(array $cookies = [], ?string $secret = null): self
    {
        return new self($cookies, $secret);
    }

    /**
     * 写入 Cookie。
     *
     * @param string $name Cookie 名
     * @param string $value Cookie 值
     * @param CookieOptions|null $options 选项
     * @return bool 是否已下发
     */
    public function set(string $name, string $value, ?CookieOptions $options = null): bool
    {
        if (headers_sent()) {
            return false;
        }

        $target = $options ?? CookieOptions::make();

        if (!setcookie($name, $value, $target->toArray())) {
            return false;
        }

        $this->cookies[$name] = $value;

        return true;
    }

    /**
     * 读取带签名的 Cookie。
     *
     * @param string $name Cookie 名
     * @param string $default 缺失或验签失败时的默认值
     * @return string Cookie 值
     */
    public function signed(string $name, string $default = ''): string
    {
        $raw = $this->get($name);

        if (strlen($raw) < self::SIGNATURE_LENGTH) {
            return $default;
        }

        $value = substr($raw, 0, -self::SIGNATURE_LENGTH);
        $signature = substr($raw, -self::SIGNATURE_LENGTH);

        if (!hash_equals($this->sign($name, $value), $signature)) {
            return $default;
        }

        return $value;
    }

    /**
     * 生成带签名的 Cookie 值。
     *
     * @param string $name Cookie 名
     * @param string $value Cookie 值
     * @return string 追加签名后的值
     */
    public function signedValue(string $name, string $value): string
    {
        return $value . $this->sign($name, $value);
    }

    /**
     * 归一 Cookie 来源。
     *
     * 只保留标量值，数组与对象直接丢弃，避免后续类型判断扩散。
     *
     * @param array<array-key, mixed> $cookies Cookie 来源
     * @return array<string, string> Cookie 镜像
     */
    private static function normalize(array $cookies): array
    {
        $normalized = [];

        foreach ($cookies as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $normalized[Type::toString($name)] = Type::toString($value);
        }

        return $normalized;
    }

    /**
     * 计算签名。
     *
     * 把 Cookie 名并入摘要，避免把一个 Cookie 的签名搬到另一个名下复用。
     *
     * @param string $name Cookie 名
     * @param string $value Cookie 值
     * @return string 签名
     */
    private function sign(string $name, string $value): string
    {
        if (null === $this->secret) {
            throw new LogicException('未配置 Cookie 签名密钥');
        }

        return hash_hmac('sha256', $name . '|' . $value, $this->secret);
    }
}
