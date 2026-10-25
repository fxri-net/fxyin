<?php

declare(strict_types=1);

namespace Fxri\Data;

/**
 * HTML 转义与清理。
 *
 * 只返回处理后的值，不输出、不退出，数组按元素递归处理。
 */
final class Html
{
    /**
     * 转义 HTML 特殊字符。
     *
     * @param mixed $value 待转义值
     * @return mixed 转义结果
     */
    public static function escape(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::escape($item), $value);
        }
        if (!is_string($value)) {
            return $value;
        }

        // 不重复编码已有实体，避免 &amp; 变成 &amp;amp;
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * 去除 HTML 标签。
     *
     * @param mixed $value 待清理值
     * @param string|null $allowed 需要保留的标签
     * @return mixed 清理结果
     */
    public static function strip(mixed $value, ?string $allowed = null): mixed
    {
        if (is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::strip($item, $allowed), $value);
        }
        if (!is_string($value)) {
            return $value;
        }

        return strip_tags($value, $allowed);
    }
}
