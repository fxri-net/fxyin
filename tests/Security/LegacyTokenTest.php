<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\LegacyToken;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 旧令牌测试：覆盖编码解码往返与非法输入。
 */
final class LegacyTokenTest extends TestCase
{
    /** 旧实现默认口令 */
    private const PASSWORD = '00000000';

    /**
     * 字符串与数组都能往返。
     */
    public function testRoundTrip(): void
    {
        $token = LegacyToken::encode('user=7&role=admin', self::PASSWORD);

        self::assertSame('user=7&role=admin', LegacyToken::decode($token, self::PASSWORD));
        self::assertSame('a,b', LegacyToken::decode(LegacyToken::encode(['a', 'b'], self::PASSWORD), self::PASSWORD));
    }

    /**
     * 空数据拒绝编码。
     */
    public function testEncodeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LegacyToken::encode('', self::PASSWORD);
    }

    /**
     * 奇数长度、非十六进制与空串都解码失败。
     */
    public function testDecodeInvalid(): void
    {
        self::assertNull(LegacyToken::decode('', self::PASSWORD));
        self::assertNull(LegacyToken::decode('abc', self::PASSWORD));
        self::assertNull(LegacyToken::decode('zz', self::PASSWORD));
    }

    /**
     * 口令错误时解码失败返回 null。
     */
    public function testDecodeWrongPassword(): void
    {
        $token = LegacyToken::encode('payload', self::PASSWORD);

        self::assertNull(LegacyToken::decode($token, '12345678'));
    }
}
