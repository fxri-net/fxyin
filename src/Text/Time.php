<?php

declare(strict_types=1);

namespace Fxri\Text;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * 时间处理。
 *
 * 内部统一用 Unix 时间戳，时区取 PHP 默认时区。
 */
final class Time
{
    /**
     * 按粒度格式化时间戳。
     *
     * @param int $timestamp 时间戳
     * @param DateGranularity $granularity 粒度
     * @return string 格式化结果
     */
    public static function format(int $timestamp, DateGranularity $granularity = DateGranularity::DateTime): string
    {
        return date($granularity->format(), $timestamp);
    }

    /**
     * 取毫秒时间戳。
     *
     * @param int|null $timestamp 秒级时间戳，null 表示当前时间
     * @return int 毫秒时间戳
     */
    public static function milli(?int $timestamp = null): int
    {
        if (null !== $timestamp) {
            return $timestamp * 1000;
        }

        return (int) round(microtime(true) * 1000);
    }

    /**
     * 把时间戳或时间串解析为时间戳。
     *
     * @param int|string $value 时间戳或时间串
     * @return int 时间戳
     */
    public static function parse(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        $parsed = strtotime($value);
        if (false === $parsed) {
            throw new InvalidArgumentException('无法解析的时间：' . $value);
        }

        return $parsed;
    }

    /**
     * 把时间戳截断到粒度的起点。
     *
     * @param int $timestamp 时间戳
     * @param DateGranularity $granularity 粒度
     * @return int 截断后的时间戳
     */
    public static function truncate(int $timestamp, DateGranularity $granularity = DateGranularity::DateTime): int
    {
        $date = (new DateTimeImmutable('@' . $timestamp))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));

        return match ($granularity) {
            DateGranularity::DateTime => $timestamp,
            DateGranularity::Date => $date->setTime(0, 0)->getTimestamp(),
            DateGranularity::Time => $date->setDate(1970, 1, 1)->getTimestamp(),
        };
    }
}
