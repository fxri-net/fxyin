<?php

declare(strict_types=1);

namespace Fxri\Config;

use Fxri\Param\Merger;

/**
 * 可写配置。
 *
 * 以只读 `Config` 为基底，运行期写入单独存放在覆盖层；读取先查覆盖层，再查基底。
 */
final class Store
{
    /** @var array<array-key, mixed> 运行期覆盖 */
    private array $overrides;

    /**
     * 初始化。
     *
     * @param Config $base 基底配置
     * @param array<array-key, mixed> $overrides 运行期覆盖
     */
    private function __construct(
        private readonly Config $base,
        array $overrides = [],
    ) {
        $this->overrides = $overrides;
    }

    /**
     * 返回合并覆盖层后的全部配置。
     *
     * @return array<array-key, mixed> 配置数据
     */
    public function all(): array
    {
        return Merger::merge($this->base->all(), $this->overrides);
    }

    /**
     * 返回基底配置。
     *
     * @return Config 只读配置
     */
    public function base(): Config
    {
        return $this->base;
    }

    /**
     * 按点号路径读取配置。
     *
     * 覆盖层存在该路径时优先返回覆盖值，否则回落到基底；路径规则与 `Config::get()` 一致。
     *
     * @param string $name 点号分隔的路径
     * @param mixed $default 缺失时的默认值
     * @return mixed 配置值
     */
    public function get(string $name, mixed $default = null): mixed
    {
        $result = Path::read($this->overrides, $name);

        return $result['found'] ? $result['value'] : $this->base->get($name, $default);
    }

    /**
     * 判断路径是否存在。
     *
     * 覆盖层或基底任一存在即视为存在。
     *
     * @param string $name 点号分隔的路径
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return Path::read($this->overrides, $name)['found'] || $this->base->has($name);
    }

    /**
     * 创建实例。
     *
     * @param Config|null $base 基底配置，缺省为空配置
     * @param array<array-key, mixed> $overrides 运行期覆盖
     * @return self 配置存储
     */
    public static function make(?Config $base = null, array $overrides = []): self
    {
        return new self($base ?? Config::make(), $overrides);
    }

    /**
     * 写入配置。
     *
     * 字符串路径按点号展开，数组整体递归合并；只写覆盖层，基底保持不变。
     *
     * @param string|array<array-key, mixed> $name 点号路径或配置数组
     * @param mixed $value 配置值，传数组时忽略
     * @return void
     */
    public function set(string|array $name, mixed $value = null): void
    {
        if (is_array($name)) {
            $this->overrides = Merger::merge($this->overrides, $name);

            return;
        }

        $this->overrides = Merger::merge($this->overrides, Path::nest($name, $value));
    }
}
