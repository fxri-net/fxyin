<?php

declare(strict_types=1);

namespace Fxri\Support;

use InvalidArgumentException;

/**
 * 随机源。
 *
 * 统一走密码学安全随机，替代旧实现里的 rand、mt_rand 与 uniqid 组合。
 */
final class Random
{
    /**
     * 生成随机字节串。
     *
     * @param int $length 字节数
     * @return string 二进制串
     */
    public static function bytes(int $length): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('随机字节长度必须大于 0');
        }

        return random_bytes($length);
    }

    /**
     * 生成随机整数，含两端。
     *
     * @param int $minimum 最小值
     * @param int $maximum 最大值
     * @return int 随机结果
     */
    public static function integer(int $minimum, int $maximum): int
    {
        return random_int($minimum, $maximum);
    }

    /**
     * 生成随机字符串。
     *
     * `$firstCharset` 单独限定首字符，用于需要以字母开头的标识；缺省与其余字符同一字符集。
     *
     * @param int $length 字符个数
     * @param RandomCharset $charset 字符集
     * @param RandomCharset|null $firstCharset 首字符集，null 表示与 `$charset` 相同
     * @return string 随机串
     */
    public static function string(int $length, RandomCharset $charset = RandomCharset::Alnum, ?RandomCharset $firstCharset = null): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('随机字符串长度必须大于 0');
        }

        $first = $firstCharset ?? $charset;
        $result = self::pick($first);
        for ($index = 1; $index < $length; ++$index) {
            $result .= self::pick($charset);
        }

        return $result;
    }

    /**
     * 从字符集取一个随机字符。
     *
     * @param RandomCharset $charset 字符集
     * @return string 单个字符
     */
    private static function pick(RandomCharset $charset): string
    {
        $characters = $charset->characters();

        return $characters[random_int(0, $charset->length() - 1)];
    }
}
