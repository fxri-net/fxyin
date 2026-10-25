<?php

declare(strict_types=1);

namespace Fxri\Sql\Dialect;

use Fxri\Sql\Expression;
use Fxri\Sql\Sql;
use InvalidArgumentException;

/**
 * MySQL 方言。
 *
 * 字段名一律走白名单校验后加反引号，函数名大写，值全部交给占位符绑定。
 */
final class MysqlDialect implements DialectInterface
{
    /**
     * 初始化。
     */
    private function __construct()
    {
    }

    /**
     * 包装字段名。
     *
     * @param string $name 字段名，支持表名前缀
     * @return string 带反引号的字段名
     */
    public function field(string $name): string
    {
        $parts = explode('.', $name);
        if (2 < count($parts)) {
            throw new InvalidArgumentException('字段名不合法：' . $name);
        }

        $wrapped = [];
        foreach ($parts as $part) {
            $wrapped[] = '*' === $part ? '*' : $this->quote($part, $name);
        }

        return implode('.', $wrapped);
    }

    /**
     * 生成集合查找片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 待查找的值
     * @return Sql 片段
     */
    public function findInSet(string|Expression $column, mixed $value): Sql
    {
        return Sql::make('FIND_IN_SET(?, ' . $this->column($column) . ')', [$value]);
    }

    /**
     * 生成时间戳格式化片段。
     *
     * @param string|Expression $column 字段
     * @param string $format 输出格式
     * @return Sql 片段
     */
    public function fromUnixtime(string|Expression $column, string $format = '%Y-%m-%d %H:%i:%s'): Sql
    {
        return Sql::make('FROM_UNIXTIME(' . $this->column($column) . ', ?)', [$format]);
    }

    /**
     * 生成空值兜底片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $default 兜底值
     * @return Sql 片段
     */
    public function ifnull(string|Expression $column, mixed $default): Sql
    {
        // 兜底值本身为空时没有绑定意义，直接写 NULL 让语句保持可读
        if (null === $default) {
            return Sql::make('IFNULL(' . $this->column($column) . ', NULL)');
        }

        return Sql::make('IFNULL(' . $this->column($column) . ', ?)', [$default]);
    }

    /**
     * 生成 JSON 包含片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 待匹配的结构
     * @return Sql 片段
     */
    public function jsonContains(string|Expression $column, mixed $value): Sql
    {
        $json = json_encode($value);
        if (false === $json) {
            throw new InvalidArgumentException('JSON 值无法编码');
        }

        return Sql::make('JSON_CONTAINS(' . $this->column($column) . ', ?)', [$json]);
    }

    /**
     * 创建实例。
     *
     * @return self 方言
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 取参数占位符。
     *
     * @return string 占位符
     */
    public function placeholder(): string
    {
        return '?';
    }

    /**
     * 取字段片段。
     *
     * @param string|Expression $column 字段
     * @return string 字段片段
     */
    private function column(string|Expression $column): string
    {
        return $column instanceof Expression ? $column->sql() : $this->field($column);
    }

    /**
     * 校验并包装单段字段名。
     *
     * @param string $part 单段字段名
     * @param string $name 原始字段名，用于报错
     * @return string 带反引号的字段名
     */
    private function quote(string $part, string $name): string
    {
        if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
            throw new InvalidArgumentException('字段名不合法：' . $name);
        }

        return '`' . $part . '`';
    }
}
