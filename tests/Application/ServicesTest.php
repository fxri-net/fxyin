<?php

declare(strict_types=1);

namespace Fxri\Tests\Application;

use Fxri\Application\Services;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * 服务注册表测试：实例、工厂、缓存与缺失行为。
 */
final class ServicesTest extends TestCase
{
    /**
     * 登记实例后可直接解析。
     */
    public function testSetAndGet(): void
    {
        $service = new stdClass();
        $services = Services::make();

        $services->set('demo', $service);

        self::assertTrue($services->has('demo'));
        self::assertSame($service, $services->get('demo'));
        self::assertSame(['demo'], $services->ids());
    }

    /**
     * 工厂首次解析后缓存，只执行一次。
     */
    public function testFactoryIsLazyAndCached(): void
    {
        $services = Services::make();
        $calls = 0;
        $services->factory('demo', static function (Services $registry) use (&$calls, $services): object {
            ++$calls;
            self::assertSame($services, $registry);

            return new stdClass();
        });

        self::assertTrue($services->has('demo'));
        self::assertSame(0, $calls);

        $first = $services->get('demo');
        $second = $services->get('demo');

        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    /**
     * 实例覆盖既有工厂，工厂覆盖既有实例。
     */
    public function testRegistrationOrder(): void
    {
        $services = Services::make();
        $services->factory('demo', static fn (): object => new stdClass());
        $service = new stdClass();
        $services->set('demo', $service);

        self::assertSame($service, $services->get('demo'));

        $services->factory('demo', static fn (): object => new stdClass());

        self::assertNotSame($service, $services->get('demo'));
        self::assertSame(['demo'], $services->ids());
    }

    /**
     * 未登记的服务抛异常。
     */
    public function testMissingService(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('服务未注册');

        Services::make()->get('missing');
    }

    /**
     * 工厂返回非对象时抛异常。
     */
    public function testFactoryMustReturnObject(): void
    {
        $services = Services::make();
        $services->factory('demo', static fn (): string => 'invalid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('服务工厂必须返回对象');

        $services->get('demo');
    }
}
