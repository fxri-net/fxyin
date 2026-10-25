<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\CaseFormat;
use Fxri\Http\ClientInfo;
use Fxri\Http\IpSource;
use Fxri\Http\ServerContext;
use PHPUnit\Framework\TestCase;

/**
 * 访问者信息测试：覆盖地址取信与 UA 识别。
 */
final class ClientInfoTest extends TestCase
{
    /**
     * 默认不采信转发头。
     */
    public function testForwardedIgnoredByDefault(): void
    {
        $info = ClientInfo::make(ServerContext::make([
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]));

        self::assertSame('10.0.0.5', $info->ip());
    }

    /**
     * 对端命中可信网段时采信转发头。
     */
    public function testForwardedAcceptedFromTrustedProxy(): void
    {
        $info = ClientInfo::make(
            ServerContext::make([
                'REMOTE_ADDR' => '10.0.0.5',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            ]),
            IpSource::Forwarded,
            ['10.0.0.0/8'],
        );

        self::assertSame('203.0.113.9', $info->ip());
        self::assertTrue($info->isTrustedProxy());
    }

    /**
     * 对端不在可信网段时忽略转发头。
     */
    public function testForwardedIgnoredFromUntrustedProxy(): void
    {
        $info = ClientInfo::make(
            ServerContext::make([
                'REMOTE_ADDR' => '198.51.100.1',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            ]),
            IpSource::Forwarded,
            ['10.0.0.0/8'],
        );

        self::assertSame('198.51.100.1', $info->ip());
        self::assertFalse($info->isTrustedProxy());
    }

    /**
     * 从右向左跳过可信跳点，取第一个外部地址。
     */
    public function testForwardedSkipsTrustedHops(): void
    {
        $info = ClientInfo::make(
            ServerContext::make([
                'REMOTE_ADDR' => '10.0.0.5',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 10.0.0.7',
            ]),
            IpSource::Forwarded,
            ['10.0.0.0/8'],
        );

        self::assertSame('203.0.113.9', $info->ip());
    }

    /**
     * 非法候选地址回落到对端地址。
     */
    public function testInvalidForwardedFallsBack(): void
    {
        $info = ClientInfo::make(
            ServerContext::make([
                'REMOTE_ADDR' => '10.0.0.5',
                'HTTP_X_FORWARDED_FOR' => 'unknown, not-an-ip',
            ]),
            IpSource::Forwarded,
            ['10.0.0.0/8'],
        );

        self::assertSame('10.0.0.5', $info->ip());
    }

    /**
     * 缺对端地址时回落到占位地址。
     */
    public function testUnknownRemote(): void
    {
        self::assertSame('0.0.0.0', ClientInfo::make(ServerContext::make())->ip());
    }

    /**
     * 按 UA 识别浏览器与系统。
     */
    public function testUserAgent(): void
    {
        $agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
        $info = ClientInfo::make(ServerContext::make(['HTTP_USER_AGENT' => $agent]));

        self::assertSame('Google Chrome', $info->browser());
        self::assertSame('google chrome', $info->browser(CaseFormat::Lower));
        self::assertSame('GOOGLE CHROME', $info->browser(CaseFormat::Upper));
        self::assertSame('Windows', $info->system());
        self::assertSame('windows', $info->system(CaseFormat::Lower));
        self::assertSame($agent, $info->userAgent());
    }

    /**
     * 未知 UA 返回缺省名称。
     */
    public function testUnknownAgent(): void
    {
        $info = ClientInfo::make(ServerContext::make());

        self::assertSame('Others', $info->browser());
        self::assertSame('Others', $info->system());
    }
}
