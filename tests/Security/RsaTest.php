<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\Rsa;
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
     * 公钥不合法时拒绝加密。
     */
    public function testInvalidPublicKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rsa::encrypt('风音', 'not-a-key');
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
