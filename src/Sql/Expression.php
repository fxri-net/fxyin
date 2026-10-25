<?php

declare(strict_types=1);

namespace Fxri\Sql;

use InvalidArgumentException;

/**
 * 原生表达式。
 *
 * 构造即视为可信片段，不再转义，调用方无法把普通字符串误当表达式传入。
 */
final class Expression
{
    /**
     * 初始化。
     *
     * @param string $sql 原生片段
     */
    private function __construct(
        private readonly string $sql,
    ) {
        if ('' === trim($sql)) {
            throw new InvalidArgumentException('表达式不能为空');
        }
    }

    /**
     * 创建实例。
     *
     * @param string $sql 原生片段
     * @return self 表达式
     */
    public static function make(string $sql): self
    {
        return new self($sql);
    }

    /**
     * 取原生片段。
     *
     * @return string 原生片段
     */
    public function sql(): string
    {
        return $this->sql;
    }
}
