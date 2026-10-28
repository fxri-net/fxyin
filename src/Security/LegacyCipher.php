<?php

declare(strict_types=1);

namespace Fxri\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * 旧数据读写。
 *
 * 只用于读写 1.x 以单 DES 加密的数据，新数据一律走 Cipher。
 * 现代 OpenSSL 已移除单 DES，这里用三枚子密钥相同的 3DES-EDE 等价还原。
 */
final class LegacyCipher
{
    /** 加密算法 */
    private const ALGORITHM = 'des-ede3-ecb';

    /** 单 DES 密钥长度 */
    private const KEY_LENGTH = 8;

    /**
     * 加密历史格式数据。
     *
     * @param string $plain 明文
     * @param string|null $password 旧口令
     * @return string base64 密文
     */
    public static function encrypt(#[\SensitiveParameter] string $plain, #[\SensitiveParameter] ?string $password = null): string
    {
        if (null === $password || '' === $password) {
            throw new InvalidArgumentException('旧数据加密必须提供口令');
        }

        $cipher = openssl_encrypt($plain, self::ALGORITHM, self::deriveKey($password), 0, '');
        if (false === $cipher) {
            throw new RuntimeException('旧数据加密失败');
        }

        return $cipher;
    }

    /**
     * 解密历史数据。
     *
     * @param string $payload 密文，与旧实现一致为 base64
     * @param string|null $password 旧口令，不再内置
     * @return string 明文
     */
    public static function decrypt(string $payload, #[\SensitiveParameter] ?string $password = null): string
    {
        if (null === $password || '' === $password) {
            throw new InvalidArgumentException('旧数据解密必须提供口令');
        }

        $plain = openssl_decrypt($payload, self::ALGORITHM, self::deriveKey($password), 0, '');
        if (false === $plain) {
            throw new RuntimeException('旧数据解密失败');
        }

        return $plain;
    }

    /**
     * 派生单 DES 等价密钥。
     *
     * @param string $password 旧口令
     * @return string 三枚相同子密钥拼成的 3DES 密钥
     */
    private static function deriveKey(#[\SensitiveParameter] string $password): string
    {
        $key = str_pad(substr($password, 0, self::KEY_LENGTH), self::KEY_LENGTH, "\0");

        return str_repeat($key, 3);
    }
}
