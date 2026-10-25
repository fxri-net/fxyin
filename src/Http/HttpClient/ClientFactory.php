<?php

declare(strict_types=1);

namespace Fxri\Http\HttpClient;

use InvalidArgumentException;
use ReflectionClass;
use RuntimeException;

/**
 * 客户端工厂。
 *
 * 候选实现由调用方传入，本类不硬编码第三方包名；未安装时直接报错，不静默降级。
 */
final class ClientFactory
{
    /**
     * 按候选类名探测可用实现。
     *
     * @param string ...$candidates 候选类名，按优先级排列
     * @return Client 客户端包装
     */
    public static function detect(string ...$candidates): Client
    {
        foreach ($candidates as $candidate) {
            if (self::has($candidate)) {
                return self::fromClass($candidate);
            }
        }

        throw new RuntimeException('未找到可用的 PSR-18 客户端实现');
    }

    /**
     * 按类名实例化。
     *
     * @param string $class 实现类名
     * @param array<array-key, mixed> $arguments 构造参数
     * @return Client 客户端包装
     */
    public static function fromClass(string $class, array $arguments = []): Client
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException('客户端实现未安装：' . $class);
        }

        $reflection = new ReflectionClass($class);

        return Client::make($reflection->newInstanceArgs($arguments));
    }

    /**
     * 判断实现是否可用。
     *
     * @param string $class 实现类名
     * @return bool 是否可用
     */
    public static function has(string $class): bool
    {
        return class_exists($class) && is_a($class, Client::CLIENT_INTERFACE, true);
    }
}
