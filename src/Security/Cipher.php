<?php

declare(strict_types=1);

namespace Fxri\Security;

use Fxri\Support\Random;
use InvalidArgumentException;
use RuntimeException;

/**
 * 对称加密。
 *
 * 使用 AES-256-GCM，随机 IV 与认证标签随密文一起输出，篡改密文在解密时报错。
 */
final class Cipher
{
    /** 加密算法 */
    private const ALGORITHM = 'aes-256-gcm';

    /** IV 长度 */
    private const IV_LENGTH = 12;

    /** 密钥长度 */
    private const KEY_LENGTH = 32;

    /** 认证标签长度 */
    private const TAG_LENGTH = 16;

    /** 加密密钥 */
    private readonly string $key;

    /**
     * 初始化。
     *
     * @param string $key 32 字节密钥
     */
    private function __construct(#[\SensitiveParameter] string $key)
    {
        if (self::KEY_LENGTH !== strlen($key)) {
            throw new InvalidArgumentException('密钥长度必须是 32 字节');
        }

        $this->key = $key;
    }

    /**
     * 解密。
     *
     * @param string $payload 密文
     * @return string 明文
     */
    public function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);
        if (false === $raw || strlen($raw) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new InvalidArgumentException('密文格式不合法');
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $data = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt($data, self::ALGORITHM, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $plain) {
            throw new RuntimeException('密文校验失败');
        }

        return $plain;
    }

    /**
     * 加密。
     *
     * @param string $plain 明文
     * @return string 密文
     */
    public function encrypt(#[\SensitiveParameter] string $plain): string
    {
        $iv = Random::bytes(self::IV_LENGTH);
        $tag = '';
        $data = openssl_encrypt($plain, self::ALGORITHM, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);
        if (false === $data) {
            throw new RuntimeException('加密失败');
        }

        return base64_encode($iv . $tag . $data);
    }

    /**
     * 创建实例。
     *
     * @param string $key 32 字节密钥
     * @return self 新实例
     */
    public static function make(#[\SensitiveParameter] string $key): self
    {
        return new self($key);
    }
}
