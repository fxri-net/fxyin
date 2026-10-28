<?php

declare(strict_types=1);

namespace Fxri\Text;

use Fxri\Support\Type;
use InvalidArgumentException;

/**
 * 字符串处理。
 *
 * 统一按 UTF-8 计算字符数，避免多字节字符被按字节截断。
 */
final class Str
{
    /**
     * 按分隔符拆分字符串，自动去空白与空段。
     *
     * @param string $separator 分隔符
     * @param string $value 待拆分字符串
     * @param bool $unique 是否去重
     * @return list<string> 拆分结果
     */
    public static function explode(string $separator, string $value, bool $unique = true): array
    {
        if ('' === $separator) {
            throw new InvalidArgumentException('分隔符不能为空串');
        }

        $parts = [];
        foreach (explode($separator, $value) as $part) {
            $part = trim($part);
            if ('' !== $part) {
                $parts[] = $part;
            }
        }

        return $unique ? array_values(array_unique($parts)) : $parts;
    }

    /**
     * 把值列表拼成字符串，跳过无法表达为字符串的项与空串。
     *
     * @param string $glue 连接符
     * @param array<array-key, mixed> $values 值列表
     * @param bool $unique 是否去重
     * @return string 拼装结果
     */
    public static function implode(string $glue, array $values, bool $unique = true): string
    {
        $parts = self::flatten($values);
        if ($unique) {
            $parts = array_values(array_unique($parts));
        }

        return implode($glue, $parts);
    }

    /**
     * 判断字符数是否落在区间内。
     *
     * @param string $value 待判定字符串
     * @param int|null $start 最少字符数，null 表示不限制
     * @param int|null $end 最多字符数，null 表示不限制
     * @return bool 是否满足
     */
    public static function isLength(string $value, ?int $start = null, ?int $end = null): bool
    {
        $length = self::length($value);
        if (null !== $start && $length < $start) {
            return false;
        }

        return null === $end || $length <= $end;
    }

    /**
     * 计算字符数。
     *
     * @param string $value 待计算字符串
     * @return int 字符数
     */
    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    /**
     * 追加片段。
     *
     * 默认已存在时不重复追加；`$unique` 为 false 时按 1.x `Text::splice()` 语义无条件追加。
     *
     * @param string $value 原始字符串
     * @param string $append 待追加片段
     * @param string $glue 连接符
     * @param bool $unique 是否去重
     * @return string 追加结果
     */
    public static function splice(string $value, string $append, string $glue = '', bool $unique = true): string
    {
        if ('' === $append) {
            return $value;
        }
        if ('' === $value) {
            return $append;
        }
        if (!$unique) {
            return $value . $glue . $append;
        }

        $items = '' === $glue ? [$value] : explode($glue, $value);
        if (in_array($append, $items, true)) {
            return $value;
        }

        return $value . $glue . $append;
    }

    /**
     * 把嵌套数组拉平为字符串列表。
     *
     * @param array<array-key, mixed> $values 待拉平数据
     * @return list<string> 字符串列表
     */
    private static function flatten(array $values): array
    {
        $parts = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                foreach (self::flatten($value) as $item) {
                    $parts[] = $item;
                }

                continue;
            }

            $item = Type::toString($value);
            if ('' !== $item) {
                $parts[] = $item;
            }
        }

        return $parts;
    }
}
