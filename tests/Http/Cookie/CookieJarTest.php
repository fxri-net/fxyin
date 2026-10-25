<?php

declare(strict_types=1);

namespace Fxri\Tests\Http\Cookie;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\Cookie\CookieOptions;
use Fxri\Http\Cookie\SameSite;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Cookie 容器测试：覆盖读取、归一、签名与选项输出。
 */
final class CookieJarTest extends TestCase
{
    /**
     * 读取与存在性判断。
     */
    public function testRead(): void
    {
        $jar = CookieJar::make(['theme' => 'dark', 'page' => '2']);

        self::assertSame(['theme' => 'dark', 'page' => '2'], $jar->all());
        self::assertSame('dark', $jar->get('theme'));
        self::assertSame('默认', $jar->get('missing', '默认'));
        self::assertTrue($jar->has('page'));
        self::assertFalse($jar->has('missing'));
    }

    /**
     * 非标量值被丢弃，标量值转成字符串。
     */
    public function testNormalize(): void
    {
        $jar = CookieJar::make(['list' => ['a'], 'flag' => true, 'count' => 3, 'empty' => null]);

        self::assertSame(['flag' => '1', 'count' => '3'], $jar->all());
    }

    /**
     * 签名值可原样验回。
     */
    public function testSignedRoundTrip(): void
    {
        $value = CookieJar::make([], 'secret')->signedValue('user', '42');
        $jar = CookieJar::make(['user' => $value], 'secret');

        self::assertSame('42', $jar->signed('user'));
    }

    /**
     * 换密钥后验签失败。
     */
    public function testSignedRejectsWrongSecret(): void
    {
        $value = CookieJar::make([], 'secret')->signedValue('user', '42');
        $jar = CookieJar::make(['user' => $value], 'other');

        self::assertSame('', $jar->signed('user'));
        self::assertSame('默认', $jar->signed('user', '默认'));
    }

    /**
     * 长度不足视为未签名。
     */
    public function testSignedRejectsShortValue(): void
    {
        $jar = CookieJar::make(['user' => '42'], 'secret');

        self::assertSame('', $jar->signed('user'));
    }

    /**
     * 未配置密钥时签名报错。
     */
    public function testSignedWithoutSecret(): void
    {
        $this->expectException(LogicException::class);

        CookieJar::make([])->signedValue('user', '42');
    }

    /**
     * 写入后镜像同步更新。
     */
    public function testSetUpdatesMirror(): void
    {
        if (headers_sent()) {
            self::markTestSkipped('响应头已发出，无法写入 Cookie');
        }

        $jar = CookieJar::make();

        self::assertTrue($jar->set('theme', 'dark'));
        self::assertSame('dark', $jar->get('theme'));
        self::assertTrue($jar->delete('theme'));
        self::assertFalse($jar->has('theme'));
    }

    /**
     * 选项数组按需输出。
     */
    public function testOptions(): void
    {
        self::assertSame(
            ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'],
            CookieOptions::make()->toArray(),
        );

        $custom = CookieOptions::make(100, '/admin', '.example.com', true, false, SameSite::None);

        self::assertSame(
            [
                'expires' => 100,
                'path' => '/admin',
                'httponly' => false,
                'samesite' => 'None',
                'domain' => '.example.com',
                'secure' => true,
            ],
            $custom->toArray(),
        );
    }
}
