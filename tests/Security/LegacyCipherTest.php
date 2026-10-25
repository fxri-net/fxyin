<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\LegacyCipher;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 旧数据解密测试：覆盖固定向量与口令校验。
 */
final class LegacyCipherTest extends TestCase
{
    /** 旧实现以口令 00000000 加密「风音-fxri」的结果 */
    private const PAYLOAD = 'zGWi1LQTXdVpPac4UC2BIw==';

    /**
     * 固定向量可解回原始明文。
     */
    public function testFixedVector(): void
    {
        self::assertSame('风音-fxri', LegacyCipher::decrypt(self::PAYLOAD, '00000000'));
    }

    /**
     * 未传口令时拒绝解密。
     */
    public function testMissingPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyCipher::decrypt(self::PAYLOAD);
    }

    /**
     * 空口令同样视为未提供。
     */
    public function testEmptyPassword(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LegacyCipher::decrypt(self::PAYLOAD, '');
    }

    /**
     * 口令错误时解密失败。
     */
    public function testWrongPassword(): void
    {
        $this->expectException(RuntimeException::class);
        LegacyCipher::decrypt(self::PAYLOAD, '12345678');
    }

    /**
     * 非法密文解密失败。
     */
    public function testInvalidPayload(): void
    {
        $this->expectException(RuntimeException::class);
        LegacyCipher::decrypt('!!!!', '00000000');
    }
}
