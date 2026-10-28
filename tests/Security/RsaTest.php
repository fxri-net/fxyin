<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\Rsa;
use Fxri\Security\RsaPadding;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * RSA 测试：覆盖密钥生成、签名验签与分块加解密。
 */
final class RsaTest extends TestCase
{
    /** @var array{private_key: string, public_key: string}|null 复用密钥对，避免每个用例重复生成 */
    private static ?array $pair = null;

    /**
     * 密钥强度低于下限时拒绝生成。
     */
    public function testGenerateTooSmall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rsa::generate(1024);
    }

    /**
     * 生成的密钥对为 PEM 格式。
     */
    public function testGenerate(): void
    {
        $pair = self::pair();

        self::assertStringContainsString('BEGIN PRIVATE KEY', $pair['private_key']);
        self::assertStringContainsString('BEGIN PUBLIC KEY', $pair['public_key']);
    }

    /**
     * 签名可通过验签，内容被改动后不通过。
     */
    public function testSign(): void
    {
        $pair = self::pair();
        $signature = Rsa::sign('风音-fxri', $pair['private_key']);

        self::assertTrue(Rsa::verify('风音-fxri', $signature, $pair['public_key']));
        self::assertFalse(Rsa::verify('风音-fxr', $signature, $pair['public_key']));
    }

    /**
     * 超过单块上限的明文可往返。
     */
    public function testEncrypt(): void
    {
        $pair = self::pair();
        $data = str_repeat('风音', 300);

        self::assertSame($data, Rsa::decrypt(Rsa::encrypt($data, $pair['public_key']), $pair['private_key']));
    }

    /**
     * 私钥加密的密文可由公钥解密，超长内容分块往返。
     */
    public function testPrivateEncrypt(): void
    {
        $pair = self::pair();
        $data = str_repeat('风音', 300);

        self::assertSame($data, Rsa::publicDecrypt(Rsa::privateEncrypt($data, $pair['private_key']), $pair['public_key']));
        self::assertSame('', Rsa::privateEncrypt('', $pair['private_key']));
        self::assertSame('', Rsa::publicDecrypt('', $pair['public_key']));
    }

    /**
     * 无填充模式按密钥块补齐，解密端剥离补齐字符。
     */
    public function testPrivateEncryptNoPadding(): void
    {
        $pair = self::pair();
        $data = str_repeat('风音', 300);

        $cipher = Rsa::privateEncrypt($data, $pair['private_key'], RsaPadding::NoPadding);
        self::assertSame(0, strlen($cipher) % 256);
        self::assertSame($data, Rsa::publicDecrypt($cipher, $pair['public_key'], RsaPadding::NoPadding));

        $custom = Rsa::privateEncrypt('raw', $pair['private_key'], RsaPadding::NoPadding, '*');
        self::assertSame('raw', Rsa::publicDecrypt($custom, $pair['public_key'], RsaPadding::NoPadding, '*'));
    }

    /**
     * 公钥不合法时拒绝加密。
     */
    public function testInvalidPublicKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rsa::encrypt('风音', 'not-a-key');
    }

    /**
     * 规范化后的 PEM 与标准输出一致。
     */
    public function testNormalizePem(): void
    {
        $pem = self::pair()['public_key'];
        $compact = str_replace(["\r", "\n", ' '], '', $pem);

        self::assertSame($pem, Rsa::normalizePem($pem));
        self::assertSame(str_replace(' ', '', $pem), Rsa::normalizePem($compact));
    }

    /**
     * 空内容被拒绝。
     */
    public function testNormalizeEmptyPem(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PEM 内容为空');

        Rsa::normalizePem('-----BEGIN PUBLIC KEY----------END PUBLIC KEY-----');
    }

    /**
     * 返回测试用密钥对。
     *
     * @return array{private_key: string, public_key: string} PEM 格式密钥对
     */
    private static function pair(): array
    {
        if (null === self::$pair) {
            self::$pair = Rsa::generate();
        }

        return self::$pair;
    }
}
