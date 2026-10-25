<?php

declare(strict_types=1);

namespace Fxri\Support;

use DateTimeImmutable;
use JsonException;
use Stringable;

/**
 * 类型判定与标量转换。
 *
 * 只做无副作用的判断与转换，不抛异常、不做本地化处理，供各层复用。
 */
final class Type
{
    /**
     * 判断是否为空值。
     *
     * 沿用旧实现的语义：null 与空字符串都算空，0 与 false 不算空。
     *
     * @param mixed $value 待判定值
     * @return bool 是否为空
     */
    public static function isBlank(mixed $value): bool
    {
        return null === $value || '' === $value;
    }

    /**
     * 判断是否为可调用结构。
     *
     * @param mixed $value 待判定值
     * @return bool 是否可调用
     */
    public static function isCallable(mixed $value): bool
    {
        return is_callable($value);
    }

    /**
     * 判断是否为邮箱地址。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isEmail(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    /**
     * 判断是否为 IP 地址。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isIp(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP);
    }

    /**
     * 判断是否为 IPv4 地址。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isIpv4(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    /**
     * 判断是否为 IPv6 地址。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isIpv6(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    /**
     * 判断是否为 JSON 数组或对象串。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isJson(string $value): bool
    {
        if ('' === trim($value)) {
            return false;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return is_array($decoded);
    }

    /**
     * 判断是否为中国大陆手机号。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isMobile(string $value): bool
    {
        return 1 === preg_match('/^1\d{10}$/', $value);
    }

    /**
     * 判断是否为指定格式的时间串。
     *
     * @param string $value 待判定值
     * @param string $format 日期格式
     * @return bool 是否合法
     */
    public static function isTime(string $value, string $format = 'Y-m-d H:i:s'): bool
    {
        $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if (false === $date) {
            return false;
        }

        return $date->format($format) === $value;
    }

    /**
     * 判断是否为网址。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isUrl(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_URL);
    }

    /**
     * 判断是否为六位邮政编码。
     *
     * @param string $value 待判定值
     * @return bool 是否合法
     */
    public static function isZipcode(string $value): bool
    {
        return 1 === preg_match('/^[1-9]\d{5}$/', $value);
    }

    /**
     * 转换为布尔值，兼容常见字符串写法。
     *
     * @param mixed $value 待转换值
     * @return bool 转换结果
     */
    public static function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($normalized, ['', '0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * 转换为浮点数，非数值返回 0。
     *
     * @param mixed $value 待转换值
     * @return float 转换结果
     */
    public static function toFloat(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * 转换为整数，非数值返回 0。
     *
     * @param mixed $value 待转换值
     * @return int 转换结果
     */
    public static function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * 转换为字符串。
     *
     * 数组、对象等无法直接表达的值返回空串，避免触发隐式转换告警。
     *
     * @param mixed $value 待转换值
     * @return string 转换结果
     */
    public static function toString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}
