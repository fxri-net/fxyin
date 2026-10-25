<?php

declare(strict_types=1);

namespace Fxri\Tests\Integration\ThinkPHP;

use Fxri\Integration\ThinkPHP\ThinkPHPAdapter;
use Fxri\KernelBuilder;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * ThinkPHP 适配测试：容器绑定、解析与缺失能力时的拒绝。
 */
final class ThinkPHPAdapterTest extends TestCase
{
    /**
     * 测试环境未安装 ThinkPHP，可用性判定为假。
     */
    public function testIsAvailable(): void
    {
        self::assertFalse(ThinkPHPAdapter::isAvailable());
    }

    /**
     * 按默认标识绑定后可按默认标识取回同一内核。
     */
    public function testBindAndKernel(): void
    {
        $container = self::container();
        $kernel = KernelBuilder::make()->build();

        ThinkPHPAdapter::bind($container, $kernel);

        self::assertSame($kernel, ThinkPHPAdapter::kernel($container));
    }

    /**
     * 自定义标识独立于默认标识。
     */
    public function testCustomId(): void
    {
        $container = self::container();
        $kernel = KernelBuilder::make()->build();

        ThinkPHPAdapter::bind($container, $kernel, 'demo.kernel');

        self::assertSame($kernel, ThinkPHPAdapter::kernel($container, 'demo.kernel'));
    }

    /**
     * 容器缺少 instance() 时拒绝绑定。
     */
    public function testBindRejectsContainerWithoutInstance(): void
    {
        $this->expectException(LogicException::class);

        ThinkPHPAdapter::bind(new stdClass(), KernelBuilder::make()->build());
    }

    /**
     * 容器缺少 make() 时拒绝解析。
     */
    public function testKernelRejectsContainerWithoutMake(): void
    {
        $container = new class () {
            /**
             * 占位绑定方法，用于构造缺少 make() 的容器。
             *
             * @param string $id 标识
             * @param mixed $instance 实例
             * @return void
             */
            public function instance(string $id, mixed $instance): void
            {
            }
        };

        $this->expectException(LogicException::class);

        ThinkPHPAdapter::kernel($container);
    }

    /**
     * 未绑定时拒绝返回空内核。
     */
    public function testKernelRejectsUnboundId(): void
    {
        $this->expectException(LogicException::class);

        ThinkPHPAdapter::kernel(self::container());
    }

    /**
     * 构造最小容器，只保留 instance() 与 make()。
     *
     * @return object 容器
     */
    private static function container(): object
    {
        return new class () {
            /** @var array<string, mixed> 绑定集合 */
            public array $bindings = [];

            /**
             * 绑定实例。
             *
             * @param string $id 标识
             * @param mixed $instance 实例
             * @return void
             */
            public function instance(string $id, mixed $instance): void
            {
                $this->bindings[$id] = $instance;
            }

            /**
             * 解析实例。
             *
             * @param string $id 标识
             * @return mixed 实例，未绑定时为 null
             */
            public function make(string $id): mixed
            {
                return $this->bindings[$id] ?? null;
            }
        };
    }
}
