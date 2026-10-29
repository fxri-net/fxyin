<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\DesCipher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 旧数据解密测试：覆盖固定向量与口令校验。
 */
final class DesCipherTest extends TestCase
{
    /** 旧实现以口令 00000000 加密「风音-fxri」的结果 */
    private const PAYLOAD = 'zGWi1LQTXdVpPac4UC2BIw==';

    /**
     * 加密结果可由同一口令解回。
     */
    public function testEncryptRoundTrip(): void
    {
        $payload = DesCipher::encrypt('风音-fxri', '00000000');

        self::assertSame('风音-fxri', DesCipher::decrypt($payload, '00000000'));
    }

    /**
     * 未传口令时拒绝加密。
     */
    public function testEncryptMissingPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DesCipher::encrypt('风音');
    }

    /**
     * 固定向量可解回原始明文。
     */
    public function testFixedVector(): void
    {
        self::assertSame('风音-fxri', DesCipher::decrypt(self::PAYLOAD, '00000000'));
    }

    /**
     * 未传口令时拒绝解密。
     */
    public function testMissingPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DesCipher::decrypt(self::PAYLOAD);
    }

    /**
     * 空口令同样视为未提供。
     */
    public function testEmptyPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DesCipher::decrypt(self::PAYLOAD, '');
    }

    /**
     * 口令错误时解密失败。
     */
    public function testWrongPassword(): void
    {
        $this->expectException(RuntimeException::class);
        DesCipher::decrypt(self::PAYLOAD, '12345678');
    }

    /**
     * 非法密文解密失败。
     */
    public function testInvalidPayload(): void
    {
        $this->expectException(RuntimeException::class);
        DesCipher::decrypt('!!!!', '00000000');
    }
}
