<?php

declare(strict_types=1);

namespace Fxri\Security;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * RSA 加解密与签名。
 *
 * 按密钥长度自动分块，密钥强度下限 2048 位。
 */
final class Rsa
{
    /** 最小密钥位数 */
    private const MIN_BITS = 2048;

    /**
     * 解密。
     *
     * @param string $payload 密文，各块原始字节拼接后整体 base64
     * @param string $privateKey 私钥
     * @return string 明文
     */
    public static function decrypt(string $payload, #[\SensitiveParameter] string $privateKey): string
    {
        $raw = base64_decode($payload, true);
        if (false === $raw) {
            throw new InvalidArgumentException('密文格式不合法');
        }

        if ('' === $raw) {
            return '';
        }

        $key = self::privateKey($privateKey);
        $size = self::size($key);
        $result = '';
        foreach (str_split($raw, $size) as $chunk) {
            $plain = '';
            $decrypted = openssl_private_decrypt($chunk, $plain, $key, OPENSSL_PKCS1_PADDING);
            if (!$decrypted || !is_string($plain)) {
                throw new RuntimeException('RSA 解密失败');
            }

            $result .= $plain;
        }

        return $result;
    }

    /**
     * 加密。
     *
     * @param string $data 明文
     * @param string $publicKey 公钥
     * @return string 密文
     */
    public static function encrypt(#[\SensitiveParameter] string $data, string $publicKey): string
    {
        $key = self::publicKey($publicKey);
        if ('' === $data) {
            return '';
        }

        $size = self::blockSize(self::size($key), RsaPadding::Pkcs1);

        $result = '';
        foreach (str_split($data, $size) as $chunk) {
            $cipher = '';
            $encrypted = openssl_public_encrypt($chunk, $cipher, $key, OPENSSL_PKCS1_PADDING);
            if (!$encrypted || !is_string($cipher)) {
                throw new RuntimeException('RSA 加密失败');
            }

            $result .= $cipher;
        }

        return base64_encode($result);
    }

    /**
     * 生成密钥对。
     *
     * @param int $bits 密钥位数
     * @return array{private_key: string, public_key: string} PEM 格式密钥对
     */
    public static function generate(int $bits = self::MIN_BITS): array
    {
        if ($bits < self::MIN_BITS) {
            throw new InvalidArgumentException('密钥长度不能小于 2048 位');
        }

        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (false === $key) {
            throw new RuntimeException('RSA 密钥生成失败');
        }

        $private = '';
        $exported = openssl_pkey_export($key, $private);
        if (!$exported || !is_string($private)) {
            throw new RuntimeException('RSA 私钥导出失败');
        }

        $details = openssl_pkey_get_details($key);
        if (false === $details || !isset($details['key']) || !is_string($details['key'])) {
            throw new RuntimeException('RSA 公钥导出失败');
        }

        return ['private_key' => $private, 'public_key' => $details['key']];
    }

    /**
     * 规范化 PEM 内容。
     *
     * 支持传入 PEM 文本或文件路径；去掉多余空白后按 64 字符重新折行，保留头尾标记。
     *
     * @param string $pem PEM 文本或文件路径
     * @return string 规范化后的 PEM
     */
    public static function normalizePem(#[\SensitiveParameter] string $pem): string
    {
        if (is_file($pem)) {
            $contents = file_get_contents($pem);
            if (false === $contents) {
                throw new InvalidArgumentException('无法读取 PEM 文件：' . $pem);
            }
            $pem = $contents;
        }

        preg_match_all('/-----BEGIN[^-]+-----|-----END[^-]+-----/', $pem, $matches);
        $headers = $matches[0];

        $body = preg_replace('/-----BEGIN[^-]+-----|-----END[^-]+-----/', '', $pem) ?? '';
        $body = preg_replace('/\s+/', '', $body) ?? '';
        if ('' === $body) {
            throw new InvalidArgumentException('PEM 内容为空');
        }

        $lines = str_split($body, 64);
        if (2 <= count($headers)) {
            array_unshift($lines, $headers[0]);
            $lines[] = $headers[1];
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * 私钥加密。
     *
     * 用于「私钥加密、公钥解密」的原始 RSA 场景；按密钥字节数分块，`NoPadding`
     * 时先用 `$pad` 左补齐到块大小，解密端对应剥离。
     *
     * @param string $data 明文
     * @param string $privateKey 私钥
     * @param RsaPadding $padding 填充模式
     * @param string $pad 无填充时的补齐字符
     * @return string 密文原始字节
     */
    public static function privateEncrypt(#[\SensitiveParameter] string $data, #[\SensitiveParameter] string $privateKey, RsaPadding $padding = RsaPadding::Pkcs1, string $pad = "\0"): string
    {
        if ('' === $data) {
            return '';
        }

        $key = self::privateKey($privateKey);
        $size = self::size($key);
        $block = self::blockSize($size, $padding);

        $result = '';
        foreach (str_split($data, $block) as $chunk) {
            if (RsaPadding::NoPadding === $padding) {
                $chunk = str_pad($chunk, $size, $pad, STR_PAD_LEFT);
            }

            $cipher = '';
            $encrypted = openssl_private_encrypt($chunk, $cipher, $key, $padding->constant());
            if (!$encrypted || !is_string($cipher)) {
                throw new RuntimeException('RSA 私钥加密失败');
            }

            $result .= $cipher;
        }

        return $result;
    }

    /**
     * 公钥解密。
     *
     * 与 `privateEncrypt()` 对应，输入为原始密文字节；`NoPadding` 时剥离左侧补齐字符。
     *
     * @param string $payload 密文原始字节
     * @param string $publicKey 公钥
     * @param RsaPadding $padding 填充模式
     * @param string $pad 无填充时的补齐字符
     * @return string 明文
     */
    public static function publicDecrypt(string $payload, string $publicKey, RsaPadding $padding = RsaPadding::Pkcs1, string $pad = "\0"): string
    {
        if ('' === $payload) {
            return '';
        }

        $key = self::publicKey($publicKey);
        $size = self::size($key);

        $result = '';
        foreach (str_split($payload, $size) as $chunk) {
            $plain = '';
            $decrypted = openssl_public_decrypt($chunk, $plain, $key, $padding->constant());
            if (!$decrypted || !is_string($plain)) {
                throw new RuntimeException('RSA 公钥解密失败');
            }

            if (RsaPadding::NoPadding === $padding) {
                $plain = ltrim($plain, $pad);
            }

            $result .= $plain;
        }

        return $result;
    }

    /**
     * 签名。
     *
     * @param string $data 原始内容
     * @param string $privateKey 私钥
     * @return string 签名
     */
    public static function sign(#[\SensitiveParameter] string $data, #[\SensitiveParameter] string $privateKey): string
    {
        $key = self::privateKey($privateKey);
        $signature = '';
        $signed = openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$signed || !is_string($signature)) {
            throw new RuntimeException('RSA 签名失败');
        }

        return base64_encode($signature);
    }

    /**
     * 验签。
     *
     * @param string $data 原始内容
     * @param string $signature 签名
     * @param string $publicKey 公钥
     * @return bool 是否通过
     */
    public static function verify(#[\SensitiveParameter] string $data, string $signature, string $publicKey): bool
    {
        $raw = base64_decode($signature, true);
        if (false === $raw) {
            return false;
        }

        return 1 === openssl_verify($data, $raw, self::publicKey($publicKey), OPENSSL_ALGO_SHA256);
    }

    /**
     * 返回单块可容纳的明文字节数。
     *
     * 无填充模式至少保留 1 字节用于左侧补齐：补齐后整块数值必然小于模数，
     * 否则原始 RSA 会因 `m >= n` 失败。
     *
     * @param int $size 密钥字节数
     * @param RsaPadding $padding 填充模式
     * @return int<1, max> 单块明文字节数
     */
    private static function blockSize(int $size, RsaPadding $padding): int
    {
        $block = $size - max(1, $padding->overhead());
        if ($block < 1) {
            throw new RuntimeException('密钥长度不足以分块');
        }

        return $block;
    }

    /**
     * 解析私钥。
     *
     * @param string $key PEM 私钥
     * @return OpenSSLAsymmetricKey 密钥句柄
     */
    private static function privateKey(#[\SensitiveParameter] string $key): OpenSSLAsymmetricKey
    {
        $handle = openssl_pkey_get_private($key);
        if (false === $handle) {
            throw new InvalidArgumentException('私钥不合法');
        }

        return $handle;
    }

    /**
     * 解析公钥。
     *
     * @param string $key PEM 公钥
     * @return OpenSSLAsymmetricKey 密钥句柄
     */
    private static function publicKey(string $key): OpenSSLAsymmetricKey
    {
        $handle = openssl_pkey_get_public($key);
        if (false === $handle) {
            throw new InvalidArgumentException('公钥不合法');
        }

        return $handle;
    }

    /**
     * 返回密钥字节长度。
     *
     * @param OpenSSLAsymmetricKey $key 密钥句柄
     * @return int<1, max> 字节数
     */
    private static function size(OpenSSLAsymmetricKey $key): int
    {
        $details = openssl_pkey_get_details($key);
        $bits = false === $details ? 0 : ($details['bits'] ?? 0);
        $size = is_int($bits) ? intdiv($bits, 8) : 0;
        if ($size < 1) {
            throw new RuntimeException('无法读取密钥长度');
        }

        return $size;
    }
}
