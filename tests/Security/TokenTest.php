<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\Token;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 令牌测试：覆盖签发、校验、篡改与一次性消费。
 */
final class TokenTest extends TestCase
{
    /** 测试密钥 */
    private const KEY = 'token-secret';

    /**
     * 签发的令牌可校验并还原载荷。
     */
    public function testVerify(): void
    {
        $payload = Token::verify(Token::make(self::KEY, 60), self::KEY);

        self::assertNotNull($payload);
        self::assertSame(32, strlen($payload['id']));
        self::assertGreaterThan(time(), $payload['expires_at']);
    }

    /**
     * 追加内容后校验失败。
     */
    public function testTampered(): void
    {
        self::assertNull(Token::verify(Token::make(self::KEY) . 'x', self::KEY));
    }

    /**
     * 换密钥校验失败。
     */
    public function testWrongKey(): void
    {
        self::assertNull(Token::verify(Token::make(self::KEY), 'other-secret'));
    }

    /**
     * 消费回调只放行一次。
     */
    public function testConsumeOnce(): void
    {
        $token = Token::make(self::KEY);
        $used = [];
        $consume = static function (string $id) use (&$used): bool {
            if (isset($used[$id])) {
                return false;
            }

            $used[$id] = true;

            return true;
        };

        self::assertNotNull(Token::verify($token, self::KEY, $consume));
        self::assertNull(Token::verify($token, self::KEY, $consume));
    }

    /**
     * 空密钥拒绝签发。
     */
    public function testEmptyKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Token::make('');
    }

    /**
     * 有效期小于 1 秒拒绝签发。
     */
    public function testInvalidTtl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Token::make(self::KEY, 0);
    }

    /**
     * 格式不符的串直接判为无效。
     */
    public function testGarbage(): void
    {
        self::assertNull(Token::verify('garbage', self::KEY));
    }
}
