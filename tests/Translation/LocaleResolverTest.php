<?php

declare(strict_types=1);

namespace Fxri\Tests\Translation;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\ServerContext;
use Fxri\Translation\LocaleResolver;
use PHPUnit\Framework\TestCase;

/**
 * 语言检测测试：查询参数、Cookie、浏览器语言与允许列表。
 */
final class LocaleResolverTest extends TestCase
{
    /**
     * 查询参数优先，大小写归一。
     */
    public function testQueryWins(): void
    {
        $resolver = LocaleResolver::make();

        self::assertSame('en-us', $resolver->resolve(['lang' => 'EN-US'], CookieJar::make(), ServerContext::make()));
    }

    /**
     * 无查询参数时取 Cookie，Cookie 优先于浏览器语言。
     */
    public function testCookieBeforeAcceptLanguage(): void
    {
        $resolver = LocaleResolver::make();

        self::assertSame('en-us', $resolver->resolve(
            [],
            CookieJar::make(['lang' => 'en-us']),
            ServerContext::make(['HTTP_ACCEPT_LANGUAGE' => 'zh-cn,zh;q=0.9']),
        ));
    }

    /**
     * 没有查询参数与 Cookie 时读取 Accept-Language 的首段。
     */
    public function testAcceptLanguage(): void
    {
        $resolver = LocaleResolver::make();

        self::assertSame('en-us', $resolver->resolve(
            [],
            CookieJar::make(),
            ServerContext::make(['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']),
        ));
    }

    /**
     * 不在允许列表内时落回兜底语言。
     */
    public function testAllowListFallback(): void
    {
        $resolver = LocaleResolver::make(allowList: ['zh-cn'], locale: 'zh-cn');

        self::assertSame('zh-cn', $resolver->resolve(['lang' => 'en-us'], CookieJar::make(), ServerContext::make()));
    }

    /**
     * `zh-hans-cn` 归一为 `zh-cn`。
     */
    public function testChineseVariant(): void
    {
        self::assertSame('zh-cn', LocaleResolver::make()->resolve(['lang' => 'zh-hans-cn'], CookieJar::make(), ServerContext::make()));
    }

    /**
     * 参数名、Cookie 名与有效期都可替换。
     */
    public function testCustomOptions(): void
    {
        $resolver = LocaleResolver::make()
            ->withDetectVar('locale')
            ->withCookieName('locale')
            ->withCookieExpire(7200)
            ->withAllowList(['zh-cn', 'en-us'])
            ->withLocale('en-us');

        self::assertSame('locale', $resolver->detectVar());
        self::assertSame('locale', $resolver->cookieName());
        self::assertSame(7200, $resolver->cookieExpire());
        self::assertSame(['zh-cn', 'en-us'], $resolver->allowList());
        self::assertSame('en-us', $resolver->locale());
        self::assertSame('en-us', $resolver->resolve([], CookieJar::make(), ServerContext::make()));
    }
}
