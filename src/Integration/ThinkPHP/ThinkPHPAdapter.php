<?php

declare(strict_types=1);

namespace Fxri\Integration\ThinkPHP;

use Fxri\Kernel;
use LogicException;
use ReflectionMethod;

/**
 * ThinkPHP 接入适配。
 *
 * 只做容器绑定与解析两件事，不依赖 ThinkPHP 的类定义，
 * 未安装 ThinkPHP 时本类依旧可加载，调用方用 isAvailable() 决定是否启用。
 */
final class ThinkPHPAdapter
{
    /** 内核在容器中的默认标识 */
    public const KERNEL = 'fxri.kernel';

    /**
     * 判断当前运行时是否具备 ThinkPHP。
     *
     * @return bool 是否可用
     */
    public static function isAvailable(): bool
    {
        return class_exists('think\App');
    }

    /**
     * 把内核绑定进容器。
     *
     * ThinkPHP 容器的绑定入口是 instance()，用反射调用以免静态分析依赖外部类。
     *
     * @param object $container 容器
     * @param Kernel $kernel 内核
     * @param string $id 绑定标识
     * @return void
     */
    public static function bind(object $container, Kernel $kernel, string $id = self::KERNEL): void
    {
        if (!method_exists($container, 'instance')) {
            throw new LogicException('容器缺少 instance() 方法，无法绑定内核');
        }

        (new ReflectionMethod($container, 'instance'))->invoke($container, $id, $kernel);
    }

    /**
     * 从容器取回内核。
     *
     * @param object $container 容器
     * @param string $id 绑定标识
     * @return Kernel 内核
     */
    public static function kernel(object $container, string $id = self::KERNEL): Kernel
    {
        if (!method_exists($container, 'make')) {
            throw new LogicException('容器缺少 make() 方法，无法解析内核');
        }

        $resolved = (new ReflectionMethod($container, 'make'))->invoke($container, $id);

        if (!$resolved instanceof Kernel) {
            throw new LogicException(sprintf('容器中未绑定内核：%s', $id));
        }

        return $resolved;
    }
}
