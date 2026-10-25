<?php

declare(strict_types=1);

namespace Fxri\Sql;

/**
 * SQL 片段与绑定参数。
 *
 * 语句与参数分开存放，值永远不拼进 sql 字符串，交给驱动绑定。
 */
final class Sql
{
    /**
     * 初始化。
     *
     * @param string $sql 语句或片段
     * @param array<int, mixed> $bindings 绑定参数
     */
    private function __construct(
        private readonly string $sql,
        private readonly array $bindings,
    ) {
    }

    /**
     * 取绑定参数。
     *
     * @return array<int, mixed> 绑定参数
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * 判断是否为空片段。
     *
     * @return bool 是否为空
     */
    public function isEmpty(): bool
    {
        return '' === $this->sql;
    }

    /**
     * 创建实例。
     *
     * @param string $sql 语句或片段
     * @param array<int, mixed> $bindings 绑定参数
     * @return self 语句
     */
    public static function make(string $sql, array $bindings = []): self
    {
        return new self($sql, array_values($bindings));
    }

    /**
     * 取语句。
     *
     * @return string 语句或片段
     */
    public function sql(): string
    {
        return $this->sql;
    }
}
