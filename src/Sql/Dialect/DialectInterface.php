<?php

declare(strict_types=1);

namespace Fxri\Sql\Dialect;

use Fxri\Sql\Expression;
use Fxri\Sql\Sql;

/**
 * SQL 方言。
 *
 * 只收敛各数据库写法不同的函数与占位符，通用条件拼装不进入方言。
 */
interface DialectInterface
{
    /**
     * 包装字段名。
     *
     * @param string $name 字段名，支持表名前缀
     * @return string 带引用符的字段名
     */
    public function field(string $name): string;

    /**
     * 生成集合查找片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 待查找的值
     * @return Sql 片段
     */
    public function findInSet(string|Expression $column, mixed $value): Sql;

    /**
     * 生成时间戳格式化片段。
     *
     * @param string|Expression $column 字段
     * @param string $format 输出格式
     * @return Sql 片段
     */
    public function fromUnixtime(string|Expression $column, string $format = '%Y-%m-%d %H:%i:%s'): Sql;

    /**
     * 生成空值兜底片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $default 兜底值
     * @return Sql 片段
     */
    public function ifnull(string|Expression $column, mixed $default): Sql;

    /**
     * 生成 JSON 包含片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 待匹配的结构
     * @return Sql 片段
     */
    public function jsonContains(string|Expression $column, mixed $value): Sql;

    /**
     * 取参数占位符。
     *
     * @return string 占位符
     */
    public function placeholder(): string;
}
