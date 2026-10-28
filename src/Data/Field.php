<?php

declare(strict_types=1);

namespace Fxri\Data;

use Fxri\Sql\Dialect\DialectInterface;
use Fxri\Sql\Dialect\MysqlDialect;
use Fxri\Sql\Expression;
use Fxri\Sql\Sql;
use Fxri\Text\DateGranularity;
use InvalidArgumentException;

/**
 * 字段表达式。
 *
 * 只负责拼装字段片段与绑定参数，具体函数写法交给方言，调用方拿到 Sql 后自行拼接语句。
 */
final class Field
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
     * 生成字段别名。
     *
     * 字段已含分隔符时保持原样，否则补上别名前缀；以分隔符开头时去掉前导分隔符。
     *
     * @param string $field 字段
     * @param string $alias 别名
     * @param string $delimiter 分隔符
     * @return string 处理后的字段
     */
    public function alias(string $field, string $alias = '', string $delimiter = '.'): string
    {
        if ('' === $delimiter) {
            return $field;
        }
        if (!str_contains($field, $delimiter) && '' !== trim($alias)) {
            return $alias . $delimiter . $field;
        }
        if (str_starts_with($field, $delimiter)) {
            return substr($field, strlen($delimiter));
        }

        return $field;
    }

    /**
     * 生成时间字段片段。
     *
     * @param string|Expression $column 字段
     * @param DateGranularity $granularity 时间粒度
     * @param mixed $replace 兜底值，null 表示不做兜底
     * @return Sql 片段
     */
    public function date(
        string|Expression $column,
        DateGranularity $granularity = DateGranularity::DateTime,
        mixed $replace = null,
    ): Sql {
        $formatted = $this->dialect->fromUnixtime($column, $this->dateFormat($granularity));

        // 不传兜底值时省掉 IF 分支，语句更短也更易读
        if (null === $replace) {
            return $formatted;
        }

        $field = $this->column($column);

        return Sql::make(
            'IF(' . $field . ' = 0 OR ' . $field . ' IS NULL, ?, ' . $formatted->sql() . ')',
            array_merge([$replace], $formatted->bindings()),
        );
    }

    /**
     * 生成经纬度距离片段。
     *
     * @param string|Expression $lngStart 起点经度
     * @param string|Expression $latStart 起点纬度
     * @param string|Expression $lngEnd 终点经度
     * @param string|Expression $latEnd 终点纬度
     * @return Sql 片段，单位为公里
     */
    public function distance(
        string|Expression $lngStart,
        string|Expression $latStart,
        string|Expression $lngEnd,
        string|Expression $latEnd,
    ): Sql {
        return Sql::make(Expression::distance(
            $this->column($lngStart),
            $this->column($latStart),
            $this->column($lngEnd),
            $this->column($latEnd),
        )->sql());
    }

    /**
     * 生成安全除法片段。
     *
     * @param string|Expression $dividend 被除数
     * @param string|Expression $divisor 除数
     * @return Sql 片段
     */
    public function division(string|Expression $dividend, string|Expression $divisor): Sql
    {
        return Sql::make(Expression::division($this->column($dividend), $this->column($divisor))->sql());
    }

    /**
     * 转义字段名。
     *
     * 已带反引号的字段先去掉引号再统一包装，重复调用不会叠加引号。
     *
     * @param string $field 字段名
     * @param string $delimiter 分隔符
     * @return string 带引号的字段名
     */
    public function escape(string $field, string $delimiter = '.'): string
    {
        $bare = str_replace('`', '', $field);
        if ('' !== $delimiter) {
            $bare = str_replace($delimiter, '.', $bare);
        }

        return $this->dialect->field($bare);
    }

    /**
     * 生成中文首字母片段。
     *
     * @param string|Expression $column 字段
     * @return Sql 片段
     */
    public function initial(string|Expression $column): Sql
    {
        return Sql::make(Expression::initial($this->column($column))->sql());
    }

    /**
     * 生成 JSON 字段片段。
     *
     * @param string|Expression $column 字段
     * @param string $path JSON 路径，支持 a.b 与 $.a.b 两种写法
     * @param FieldJsonMode $mode 路径模式
     * @param mixed $replace 兜底值
     * @return Sql 片段
     */
    public function json(
        string|Expression $column,
        string $path,
        FieldJsonMode $mode = FieldJsonMode::Quoted,
        mixed $replace = '',
    ): Sql {
        if ('' === trim($path)) {
            throw new InvalidArgumentException('JSON 路径不能为空');
        }

        $field = $this->column($column);
        $target = FieldJsonMode::Raw === $mode ? $path : $this->quotedPath($path);

        return Sql::make(
            'IF(JSON_VALID(' . $field . '), IFNULL(JSON_UNQUOTE(JSON_EXTRACT(' . $field . ', ?)), ?), ?)',
            [$target, $replace, $replace],
        );
    }

    /**
     * 创建实例。
     *
     * @param DialectInterface|null $dialect 方言，默认 MySQL
     * @return self 字段表达式
     */
    public static function make(?DialectInterface $dialect = null): self
    {
        return new self($dialect ?? MysqlDialect::make());
    }

    /**
     * 生成文本字段片段。
     *
     * @param string|Expression $column 字段
     * @param mixed $replace 兜底值
     * @param FieldTextMode $mode 文本模式
     * @return Sql 片段
     */
    public function text(
        string|Expression $column,
        mixed $replace = '',
        FieldTextMode $mode = FieldTextMode::Keep,
    ): Sql {
        $field = $this->column($column);
        $body = 'IF(LENGTH(' . $field . ') = 0 OR ' . $field . ' IS NULL, ?, ' . $field . ')';
        // Keep 沿用旧实现：兜底值是整数时同步转成整数，避免返回字符串
        $cast = FieldTextMode::CastInt === $mode || (FieldTextMode::Keep === $mode && is_int($replace));

        return Sql::make($cast ? 'CONVERT(' . $body . ', SIGNED)' : $body, [$replace]);
    }

    /**
     * 取字段片段。
     *
     * @param string|Expression $column 字段
     * @return string 字段片段
     */
    private function column(string|Expression $column): string
    {
        return $column instanceof Expression ? $column->sql() : $this->dialect->field($column);
    }

    /**
     * 取数据库时间格式。
     *
     * @param DateGranularity $granularity 时间粒度
     * @return string 格式串
     */
    private function dateFormat(DateGranularity $granularity): string
    {
        // FROM_UNIXTIME 用 MySQL 的百分号格式，和枚举里的 PHP 格式不是一套
        return match ($granularity) {
            DateGranularity::Date => '%Y-%m-%d',
            DateGranularity::DateTime => '%Y-%m-%d %H:%i:%s',
            DateGranularity::Time => '%H:%i:%s',
        };
    }

    /**
     * 生成带引号的 JSON 路径。
     *
     * @param string $path 原始路径
     * @return string 路径
     */
    private function quotedPath(string $path): string
    {
        $quoted = [];
        foreach (explode('.', ltrim($path, '$.')) as $segment) {
            if ('' === $segment) {
                throw new InvalidArgumentException('JSON 路径不合法：' . $path);
            }
            // 数组下标自带方括号，不能再加双引号
            $quoted[] = str_starts_with($segment, '[') ? $segment : '"' . $segment . '"';
        }

        return '$.' . implode('.', $quoted);
    }
}
