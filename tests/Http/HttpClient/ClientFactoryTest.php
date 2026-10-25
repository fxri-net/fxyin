<?php

declare(strict_types=1);

namespace Fxri\Tests\Http\HttpClient;

use Fxri\Http\HttpClient\Client;
use Fxri\Http\HttpClient\ClientFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * 客户端工厂测试：未安装实现包时只走失败路径，不静默降级。
 */
final class ClientFactoryTest extends TestCase
{
    /**
     * 接口名与 PSR 规范一致。
     */
    public function testInterfaceNames(): void
    {
        self::assertSame('Psr\Http\Client\ClientInterface', Client::CLIENT_INTERFACE);
        self::assertSame('Psr\Http\Message\RequestInterface', Client::REQUEST_INTERFACE);
        self::assertSame('Psr\Http\Message\ResponseInterface', Client::RESPONSE_INTERFACE);
    }

    /**
     * 未安装的实现判定为不可用。
     */
    public function testHasRejectsMissingClass(): void
    {
        self::assertFalse(ClientFactory::has(self::missingClass()));
    }

    /**
     * 未安装的实现实例化时报错。
     */
    public function testFromClassRejectsMissingClass(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ClientFactory::fromClass(self::missingClass());
    }

    /**
     * 无候选命中时报错。
     */
    public function testDetectWithoutCandidate(): void
    {
        $this->expectException(RuntimeException::class);

        ClientFactory::detect(self::missingClass());
    }

    /**
     * 非 PSR-18 对象被拒绝。
     */
    public function testMakeRejectsNonClient(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Client::make(new stdClass());
    }

    /**
     * 取一个确定不存在的类名。
     *
     * @return string 类名
     */
    private static function missingClass(): string
    {
        return 'Fxri\Tests\Http\HttpClient\MissingClient';
    }
}
