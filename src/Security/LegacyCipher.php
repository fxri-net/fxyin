<?php

declare(strict_types=1);

namespace Fxri\Security;

use InvalidArgumentException;
use RuntimeException;

/**
 * 旧数据解密。
 *
 * 只用于解开历史版本以单 DES 加密的数据，新数据一律走 Cipher。
 * 现代 OpenSSL 已移除单 DES，这里用三枚子密钥相同的 3DES-EDE 等价还原。
 */
final class LegacyCipher
{
    /** 加密算法 */
    private const ALGORITHM = 'des-ede3-ecb';

    /** 单 DES 密钥长度 */
    private const KEY_LENGTH = 8;

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

        $key = str_pad(substr($password, 0, self::KEY_LENGTH), self::KEY_LENGTH, "\0");
        $plain = openssl_decrypt($payload, self::ALGORITHM, str_repeat($key, 3), 0, '');
        if (false === $plain) {
            throw new RuntimeException('旧数据解密失败');
        }

        return $plain;
    }
}
