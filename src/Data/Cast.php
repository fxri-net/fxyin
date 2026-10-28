<?php

declare(strict_types=1);

namespace Fxri\Data;

use stdClass;

/**
 * 数组与对象转换。
 *
 * 递归把空数组转成对象、把对象转成数组，便于 JSON 编码时区分 `[]` 与 `{}`。
 */
final class Cast
{
    /**
     * 转为数组。
     *
     * 对象按属性展开，数组逐项递归；标量原样返回。
     *
     * @param mixed $value 待转换值
     * @param int $limit 递归深度，0 表示不处理，负数表示不限制
     * @return mixed 转换结果
     */
    public static function toArray(mixed $value, int $limit = -1): mixed
    {
        if (0 === $limit) {
            return $value;
        }

        $next = $limit > 0 ? $limit - 1 : -1;
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = self::toArray($item, $next);
        }

        return $result;
    }

    /**
     * 转为对象。
     *
     * 空数组转为空对象，保证 JSON 编码为 `{}`；标量原样返回。
     *
     * @param mixed $value 待转换值
     * @param int $limit 递归深度，0 表示不处理，负数表示不限制
     * @return mixed 转换结果
     */
    public static function toObject(mixed $value, int $limit = -1): mixed
    {
        if (0 === $limit || !is_array($value)) {
            return $value;
        }

        $next = $limit > 0 ? $limit - 1 : -1;
        if ([] === $value) {
            return new stdClass();
        }

        $result = new stdClass();
        foreach ($value as $key => $item) {
            $result->{(string) $key} = self::toObject($item, $next);
        }

        return $result;
    }
}
