<?php

declare(strict_types=1);

namespace Fxri\Application;

use InvalidArgumentException;
use RuntimeException;

/**
 * 服务注册表。
 *
 * 显式登记实例或工厂，按名称解析；工厂首次解析后缓存实例，不提供全局单例。
 */
final class Services
{
    /** @var array<string, object> 直接登记的实例 */
    private array $instances = [];

    /** @var array<string, callable> 工厂 */
    private array $factories = [];

    /** @var array<string, object> 已解析的实例 */
    private array $resolved = [];

    /** @var list<string> 登记顺序 */
    private array $order = [];

    /**
     * 初始化。
     */
    private function __construct()
    {
    }

    /**
     * 登记工厂。
     *
     * 工厂在首次 `get()` 时执行，参数为本注册表；返回非对象时抛异常。
     *
     * @param string $id 服务名
     * @param callable(self): object $factory 工厂
     * @return void
     */
    public function factory(string $id, callable $factory): void
    {
        $this->track($id);
        $this->factories[$id] = $factory;
        unset($this->instances[$id], $this->resolved[$id]);
    }

    /**
     * 解析服务。
     *
     * @param string $id 服务名
     * @return object 服务实例
     */
    public function get(string $id): object
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (array_key_exists($id, $this->resolved)) {
            return $this->resolved[$id];
        }

        if (!array_key_exists($id, $this->factories)) {
            throw new InvalidArgumentException('服务未注册：' . $id);
        }

        $service = ($this->factories[$id])($this);
        if (!is_object($service)) {
            throw new RuntimeException('服务工厂必须返回对象：' . $id);
        }

        $this->resolved[$id] = $service;

        return $service;
    }

    /**
     * 判断服务是否已登记或可解析。
     *
     * @param string $id 服务名
     * @return bool 是否存在
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances)
            || array_key_exists($id, $this->factories)
            || array_key_exists($id, $this->resolved);
    }

    /**
     * 返回已登记的服务名。
     *
     * @return list<string> 服务名，按登记顺序
     */
    public function ids(): array
    {
        return $this->order;
    }

    /**
     * 创建注册表。
     *
     * @return self 注册表
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 登记实例。
     *
     * @param string $id 服务名
     * @param object $service 服务实例
     * @return void
     */
    public function set(string $id, object $service): void
    {
        $this->track($id);
        $this->instances[$id] = $service;
        unset($this->factories[$id], $this->resolved[$id]);
    }

    /**
     * 记录登记顺序。
     *
     * @param string $id 服务名
     * @return void
     */
    private function track(string $id): void
    {
        if (!in_array($id, $this->order, true)) {
            $this->order[] = $id;
        }
    }
}
