<?php

declare(strict_types=1);

namespace Fxri\Sql;

use Fxri\Sql\Dialect\DialectInterface;
use Fxri\Sql\Dialect\MysqlDialect;
use InvalidArgumentException;

/**
 * 语句构建器。
 *
 * 只生成骨架语句，表名与字段名经方言校验，值全部走绑定。
 */
final class StatementBuilder
{
    /**
     * 初始化。
     *
     * @param DialectInterface $dialect 方言
     */
    private function __construct(
        private readonly DialectInterface $dialect,
    ) {
    }

    /**
     * 生成删除语句。
     *
     * @param string $table 表名
     * @param null|WhereBuilder $where 条件构建器
     * @return Sql 删除语句
     */
    public function delete(string $table, ?WhereBuilder $where = null): Sql
    {
        return $this->attach('DELETE FROM ' . $this->dialect->field($table), [], $where);
    }

    /**
     * 生成插入语句。
     *
     * @param string $table 表名
     * @param array<string, mixed> $row 字段与值
     * @return Sql 插入语句
     */
    public function insert(string $table, array $row): Sql
    {
        if ([] === $row) {
            throw new InvalidArgumentException('插入字段不能为空');
        }

        $columns = [];
        $marks = [];
        $bindings = [];
        foreach ($row as $column => $value) {
            $columns[] = $this->dialect->field($column);
            $marks[] = $this->dialect->placeholder();
            $bindings[] = $value;
        }

        return Sql::make(
            'INSERT INTO ' . $this->dialect->field($table)
            . ' (' . implode(', ', $columns) . ')'
            . ' VALUES (' . implode(', ', $marks) . ')',
            $bindings,
        );
    }

    /**
     * 创建实例。
     *
     * @param null|DialectInterface $dialect 方言，为空时使用 MySQL
     * @return self 语句构建器
     */
    public static function make(?DialectInterface $dialect = null): self
    {
        return new self($dialect ?? MysqlDialect::make());
    }

    /**
     * 生成查询语句。
     *
     * @param string $table 表名
     * @param array<int, string|Sql> $columns 查询字段，Sql 用于方言片段
     * @param null|WhereBuilder $where 条件构建器
     * @return Sql 查询语句
     */
    public function select(string $table, array $columns = ['*'], ?WhereBuilder $where = null): Sql
    {
        if ([] === $columns) {
            throw new InvalidArgumentException('查询字段不能为空');
        }

        $parts = [];
        $bindings = [];
        foreach ($columns as $column) {
            if ($column instanceof Sql) {
                $parts[] = $column->sql();
                foreach ($column->bindings() as $binding) {
                    $bindings[] = $binding;
                }
                continue;
            }
            $parts[] = $this->dialect->field($column);
        }

        $sql = 'SELECT ' . implode(', ', $parts) . ' FROM ' . $this->dialect->field($table);

        return $this->attach($sql, $bindings, $where);
    }

    /**
     * 生成更新语句。
     *
     * @param string $table 表名
     * @param array<string, mixed> $row 字段与新值
     * @param null|WhereBuilder $where 条件构建器
     * @return Sql 更新语句
     */
    public function update(string $table, array $row, ?WhereBuilder $where = null): Sql
    {
        if ([] === $row) {
            throw new InvalidArgumentException('更新字段不能为空');
        }

        $parts = [];
        $bindings = [];
        foreach ($row as $column => $value) {
            $parts[] = $this->dialect->field($column) . ' = ' . $this->dialect->placeholder();
            $bindings[] = $value;
        }

        return $this->attach(
            'UPDATE ' . $this->dialect->field($table) . ' SET ' . implode(', ', $parts),
            $bindings,
            $where,
        );
    }

    /**
     * 拼接条件片段。
     *
     * @param string $sql 语句主体
     * @param array<int, mixed> $bindings 主体绑定参数
     * @param null|WhereBuilder $where 条件构建器
     * @return Sql 完整语句
     */
    private function attach(string $sql, array $bindings, ?WhereBuilder $where): Sql
    {
        if (null === $where) {
            return Sql::make($sql, $bindings);
        }

        $built = $where->build();
        if ($built->isEmpty()) {
            return Sql::make($sql, $bindings);
        }

        foreach ($built->bindings() as $binding) {
            $bindings[] = $binding;
        }

        return Sql::make($sql . ' WHERE ' . $built->sql(), $bindings);
    }
}
