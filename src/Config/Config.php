<?php

declare(strict_types=1);

namespace Fxri\Config;

/**
 * 只读配置容器。
 *
 * 数据在加载期由 ConfigLoader 组织完成，构造后不再变化，运行期只能读取。
 */
final class Config
{
    /**
     * 初始化。
     *
     * @param array<array-key, mixed> $data 配置数据
     */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * 返回全部配置。
     *
     * @return array<array-key, mixed> 配置数据
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 按点号路径读取配置。
     *
     * 路径段中的 `/_` 会还原为点，用于键名本身含点的场景。
     *
     * @param string $name 点号分隔的路径
     * @param mixed $default 缺失时的默认值
     * @return mixed 配置值
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $result = Path::read($this->data, $name);

        return $result['found'] ? $result['value'] : $default;
    }

    /**
     * 判断路径是否存在。
     *
     * 以键存在为准，值为 null 也算存在。
     *
     * @param string $name 点号分隔的路径
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return Path::read($this->data, $name)['found'];
    }

    /**
     * 创建实例。
     *
     * @param array<array-key, mixed> $data 配置数据
     * @return self 配置实例
     */
    public static function make(array $data = []): self
    {
        return new self($data);
    }
}
