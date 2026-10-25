<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\Cipher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 对称加密测试：覆盖往返、随机 IV 与认证失败。
 */
final class CipherTest extends TestCase
{
    /** 测试密钥 */
    private const KEY = '0123456789abcdef0123456789abcdef';

    /**
     * 同一密钥可还原明文。
     */
    public function testRoundTrip(): void
    {
        $cipher = Cipher::make(self::KEY);

        self::assertSame('风音-fxri', $cipher->decrypt($cipher->encrypt('风音-fxri')));
    }

    /**
     * 同一明文两次加密的密文不同。
     */
    public function testRandomIv(): void
    {
        $cipher = Cipher::make(self::KEY);

        self::assertNotSame($cipher->encrypt('风音'), $cipher->encrypt('风音'));
    }

    /**
     * 篡改密文触发认证失败。
     */
    public function testTampered(): void
    {
        $cipher = Cipher::make(self::KEY);
        $payload = $cipher->encrypt('风音');
        $tampered = ('A' === $payload[0] ? 'B' : 'A') . substr($payload, 1);

        $this->expectException(RuntimeException::class);
        $cipher->decrypt($tampered);
    }

    /**
     * 换密钥无法解密。
     */
    public function testWrongKey(): void
    {
        $payload = Cipher::make(self::KEY)->encrypt('风音');

        $this->expectException(RuntimeException::class);
        Cipher::make(str_repeat('k', 32))->decrypt($payload);
    }

    /**
     * 密钥长度不符时拒绝创建。
     */
    public function testInvalidKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cipher::make('short');
    }

    /**
     * 密文不是合法 base64 时拒绝解密。
     */
    public function testInvalidPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Cipher::make(self::KEY)->decrypt('!!!!');
    }
}
