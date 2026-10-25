<?php

declare(strict_types=1);

namespace Fxri\Sql;

use Closure;
use Fxri\Sql\Dialect\DialectInterface;
use Fxri\Sql\Dialect\MysqlDialect;
use InvalidArgumentException;

/**
 * 条件构建器。
 *
 * 值只进入绑定数组，连接方式与取反标记作用于紧随其后的条件。
 */
final class WhereBuilder
{
    /** @var list<array{boolean: Boolean, condition: Sql, negate: bool}> 条件列表 */
    private array $conditions = [];

    /** 下一个条件的连接方式 */
    private Boolean $nextBoolean = Boolean::And;

    /** 下一个条件是否取反 */
    private bool $nextNegate = false;

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
     * 下一个条件用 AND 连接。
     *
     * @return self 当前构建器
     */
    public function and(): self
    {
        $this->nextBoolean = Boolean::And;

        return $this;
    }

    /**
     * 追加区间条件。
     *
     * @param string|Expression $column 字段
     * @param array<int, mixed> $value 恰好两个边界值
     * @return self 当前构建器
     */
    public function between(string|Expression $column, array $value): self
    {
        return $this->compare(WhereOperator::Between, $column, $value);
    }

    /**
     * 生成片段。
     *
     * @return Sql 条件片段
     */
    public function build(): Sql
    {
        return $this->render();
    }

    /**
     * 追加任意比较条件。
     *
     * @param WhereOperator $operator 比较方式
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @param Collation $collation 字符串比较的大小写策略
     * @return self 当前构建器
     */
    public function compare(
        WhereOperator $operator,
        string|Expression $column,
        mixed $value = null,
        Collation $collation = Collation::CaseInsensitive,
    ): self {
        $field = $column instanceof Expression ? $column->sql() : $this->dialect->field($column);
        [$fragment, $bindings] = $this->fragment($operator, $field, $value, $collation);
        $this->push(Sql::make($fragment, $bindings));

        return $this;
    }

    /**
     * 追加等于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @param Collation $collation 字符串比较的大小写策略
     * @return self 当前构建器
     */
    public function eq(
        string|Expression $column,
        mixed $value,
        Collation $collation = Collation::CaseInsensitive,
    ): self {
        return $this->compare(WhereOperator::Eq, $column, $value, $collation);
    }

    /**
     * 追加以括号包裹的分组条件。
     *
     * @param Closure(WhereBuilder): void $callback 子条件回调
     * @param null|Boolean $boolean 与前一条件的连接方式，为空时沿用当前连接方式
     * @return self 当前构建器
     */
    public function group(Closure $callback, ?Boolean $boolean = null): self
    {
        $inner = self::make($this->dialect);
        $callback($inner);

        $built = $inner->build();
        // 空分组不改变连接状态，避免后续条件被悬空的连接符带偏
        if ($built->isEmpty()) {
            return $this;
        }

        if (null !== $boolean) {
            $this->nextBoolean = $boolean;
        }
        $this->push(Sql::make('(' . $built->sql() . ')', $built->bindings()));

        return $this;
    }

    /**
     * 追加大于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @return self 当前构建器
     */
    public function gt(string|Expression $column, mixed $value): self
    {
        return $this->compare(WhereOperator::Gt, $column, $value);
    }

    /**
     * 追加大于等于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @return self 当前构建器
     */
    public function gte(string|Expression $column, mixed $value): self
    {
        return $this->compare(WhereOperator::Gte, $column, $value);
    }

    /**
     * 追加集合包含条件。
     *
     * @param string|Expression $column 字段
     * @param array<int, mixed> $value 取值集合
     * @return self 当前构建器
     */
    public function in(string|Expression $column, array $value): self
    {
        return $this->compare(WhereOperator::In, $column, $value);
    }

    /**
     * 追加不为空条件。
     *
     * @param string|Expression $column 字段
     * @return self 当前构建器
     */
    public function isNotNull(string|Expression $column): self
    {
        return $this->compare(WhereOperator::NotNull, $column);
    }

    /**
     * 追加为空条件。
     *
     * @param string|Expression $column 字段
     * @return self 当前构建器
     */
    public function isNull(string|Expression $column): self
    {
        return $this->compare(WhereOperator::Null, $column);
    }

    /**
     * 追加模糊匹配条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 匹配模式
     * @param Collation $collation 字符串比较的大小写策略
     * @return self 当前构建器
     */
    public function like(
        string|Expression $column,
        mixed $value,
        Collation $collation = Collation::CaseInsensitive,
    ): self {
        return $this->compare(WhereOperator::Like, $column, $value, $collation);
    }

    /**
     * 追加小于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @return self 当前构建器
     */
    public function lt(string|Expression $column, mixed $value): self
    {
        return $this->compare(WhereOperator::Lt, $column, $value);
    }

    /**
     * 追加小于等于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @return self 当前构建器
     */
    public function lte(string|Expression $column, mixed $value): self
    {
        return $this->compare(WhereOperator::Lte, $column, $value);
    }

    /**
     * 创建实例。
     *
     * @param null|DialectInterface $dialect 方言，为空时使用 MySQL
     * @return self 条件构建器
     */
    public static function make(?DialectInterface $dialect = null): self
    {
        return new self($dialect ?? MysqlDialect::make());
    }

    /**
     * 追加不等于条件。
     *
     * @param string|Expression $column 字段
     * @param mixed $value 比较值
     * @param Collation $collation 字符串比较的大小写策略
     * @return self 当前构建器
     */
    public function ne(
        string|Expression $column,
        mixed $value,
        Collation $collation = Collation::CaseInsensitive,
    ): self {
        return $this->compare(WhereOperator::Ne, $column, $value, $collation);
    }

    /**
     * 追加以括号包裹的分组条件,沿用当前连接方式。
     *
     * @param Closure(WhereBuilder): void $callback 子条件回调
     * @return self 当前构建器
     */
    public function nested(Closure $callback): self
    {
        return $this->group($callback);
    }

    /**
     * 标记下一个条件取反。
     *
     * @return self 当前构建器
     */
    public function not(): self
    {
        $this->nextNegate = true;

        return $this;
    }

    /**
     * 下一个条件用 OR 连接。
     *
     * @return self 当前构建器
     */
    public function or(): self
    {
        $this->nextBoolean = Boolean::Or;

        return $this;
    }

    /**
     * 生成区间片段。
     *
     * @param string $field 已包装的字段
     * @param mixed $value 边界值集合
     * @return array{0: string, 1: array<int, mixed>} 片段与绑定参数
     */
    private function betweenFragment(string $field, mixed $value): array
    {
        if (!is_array($value) || 2 !== count($value)) {
            throw new InvalidArgumentException('区间条件需要恰好两个边界值');
        }

        $mark = $this->dialect->placeholder();

        return [$field . ' BETWEEN ' . $mark . ' AND ' . $mark, array_values($value)];
    }

    /**
     * 生成单个条件的片段。
     *
     * @param WhereOperator $operator 比较方式
     * @param string $field 已包装的字段
     * @param mixed $value 比较值
     * @param Collation $collation 字符串比较的大小写策略
     * @return array{0: string, 1: array<int, mixed>} 片段与绑定参数
     */
    private function fragment(WhereOperator $operator, string $field, mixed $value, Collation $collation): array
    {
        $mark = $this->dialect->placeholder();
        // 只有字符串类的比较才需要改二进制排序规则，数值比较加 BINARY 会失去索引
        $operand = Collation::CaseSensitive === $collation ? 'BINARY ' . $mark : $mark;

        return match ($operator) {
            WhereOperator::Eq => [$field . ' = ' . $operand, [$value]],
            WhereOperator::Ne => [$field . ' <> ' . $operand, [$value]],
            WhereOperator::Gt => [$field . ' > ' . $mark, [$value]],
            WhereOperator::Gte => [$field . ' >= ' . $mark, [$value]],
            WhereOperator::Lt => [$field . ' < ' . $mark, [$value]],
            WhereOperator::Lte => [$field . ' <= ' . $mark, [$value]],
            WhereOperator::Like => [$field . ' LIKE ' . $operand, [$value]],
            WhereOperator::In => $this->inFragment($field, $value),
            WhereOperator::NotNull => [$field . ' IS NOT NULL', []],
            WhereOperator::Null => [$field . ' IS NULL', []],
            WhereOperator::Between => $this->betweenFragment($field, $value),
        };
    }

    /**
     * 生成集合包含片段。
     *
     * @param string $field 已包装的字段
     * @param mixed $value 取值集合
     * @return array{0: string, 1: array<int, mixed>} 片段与绑定参数
     */
    private function inFragment(string $field, mixed $value): array
    {
        // 空集合无法构成合法的 IN 列表，用恒假条件保持语义
        if (!is_array($value) || [] === $value) {
            return ['0 = 1', []];
        }

        $marks = array_fill(0, count($value), $this->dialect->placeholder());

        return [$field . ' IN (' . implode(', ', $marks) . ')', array_values($value)];
    }

    /**
     * 追加条件并复位标记。
     *
     * @param Sql $condition 条件片段
     */
    private function push(Sql $condition): void
    {
        $this->conditions[] = [
            'boolean' => $this->nextBoolean,
            'condition' => $condition,
            'negate' => $this->nextNegate,
        ];
        $this->nextBoolean = Boolean::And;
        $this->nextNegate = false;
    }

    /**
     * 渲染全部条件。
     *
     * @return Sql 条件片段
     */
    private function render(): Sql
    {
        $parts = [];
        $bindings = [];
        foreach ($this->conditions as $index => $entry) {
            $fragment = $entry['condition']->sql();
            if ($entry['negate']) {
                $fragment = 'NOT (' . $fragment . ')';
            }
            if (0 < $index) {
                $fragment = $entry['boolean']->value . ' ' . $fragment;
            }
            $parts[] = $fragment;
            foreach ($entry['condition']->bindings() as $binding) {
                $bindings[] = $binding;
            }
        }

        return Sql::make(implode(' ', $parts), $bindings);
    }
}
