<?php

declare(strict_types=1);

namespace Fxri\Security;

use Fxri\Support\Random;
use InvalidArgumentException;
use RuntimeException;

/**
 * 令牌签发与校验。
 *
 * 载荷只携带随机标识与过期时间，签名走 HMAC-SHA256，可选一次性消费。
 */
final class Token
{
    /** 签名算法 */
    private const ALGORITHM = 'sha256';

    /** 默认有效期，单位秒 */
    private const DEFAULT_TTL = 3600;

    /** 令牌版本 */
    private const VERSION = 'v1';

    /**
     * 签发令牌。
     *
     * @param string $key 签名密钥
     * @param int $ttl 有效期，单位秒
     * @param int $bytes 标识随机字节数
     * @return string 令牌
     */
    public static function make(#[\SensitiveParameter] string $key, int $ttl = self::DEFAULT_TTL, int $bytes = 16): string
    {
        if ('' === $key) {
            throw new InvalidArgumentException('令牌签名密钥不能为空');
        }

        if ($ttl < 1) {
            throw new InvalidArgumentException('令牌有效期必须大于 0');
        }

        if ($bytes < 8) {
            throw new InvalidArgumentException('令牌标识不得少于 8 字节');
        }

        $payload = json_encode(['id' => bin2hex(Random::bytes($bytes)), 'exp' => time() + $ttl]);
        if (false === $payload) {
            throw new RuntimeException('令牌载荷编码失败');
        }

        $body = self::encode($payload);

        return self::VERSION . '.' . $body . '.' . self::sign($body, $key);
    }

    /**
     * 校验令牌。
     *
     * @param string $token 令牌
     * @param string $key 签名密钥
     * @param (callable(string): bool)|null $consume 消费回调，返回 true 表示首次使用，返回其他值视为已使用
     * @return array{id: string, expires_at: int}|null 有效时返回载荷，否则返回 null
     */
    public static function verify(string $token, #[\SensitiveParameter] string $key, ?callable $consume = null): ?array
    {
        $parts = explode('.', $token);
        if (3 !== count($parts) || self::VERSION !== $parts[0]) {
            return null;
        }

        if (!hash_equals(self::sign($parts[1], $key), $parts[2])) {
            return null;
        }

        $payload = self::decode($parts[1]);
        if (null === $payload) {
            return null;
        }

        $data = json_decode($payload, true);
        if (!is_array($data)) {
            return null;
        }

        $id = $data['id'] ?? null;
        $expires = $data['exp'] ?? null;
        if (!is_string($id) || !is_int($expires)) {
            return null;
        }

        if ($expires < time()) {
            return null;
        }

        if (null !== $consume && true !== $consume($id)) {
            return null;
        }

        return ['id' => $id, 'expires_at' => $expires];
    }

    /**
     * 解码 base64url。
     *
     * @param string $body 编码内容
     * @return string|null 原文，失败返回 null
     */
    private static function decode(string $body): ?string
    {
        $raw = base64_decode(strtr($body, '-_', '+/'), true);

        return false === $raw ? null : $raw;
    }

    /**
     * 编码 base64url。
     *
     * @param string $raw 原文
     * @return string 去掉填充的编码结果
     */
    private static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * 计算签名。
     *
     * 版本号并入签名内容，避免令牌被换成其他版本的格式后仍能通过校验。
     *
     * @param string $body 载荷
     * @param string $key 签名密钥
     * @return string 签名
     */
    private static function sign(string $body, #[\SensitiveParameter] string $key): string
    {
        return self::encode(hash_hmac(self::ALGORITHM, self::VERSION . '.' . $body, $key, true));
    }
}
