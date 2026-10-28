<?php

declare(strict_types=1);

namespace Fxri\Text;

use DateTimeImmutable;
use DateTimeZone;
use Fxri\Support\Type;
use InvalidArgumentException;

/**
 * 时间处理。
 *
 * 内部统一用 Unix 时间戳，时区取 PHP 默认时区。
 */
final class Time
{
    /**
     * 转换时间。
     *
     * 字符串先解析为时间戳；类型 `1` 按格式截断后返回时间戳，`2` 返回格式化字符串，
     * 其它值返回时间戳；无法解析时返回 null。
     *
     * @param int|string $value 时间戳或时间串
     * @param string|null $type 转换类型
     * @param string $format 格式
     * @return int|string|null 转换结果
     */
    public static function change(int|string $value, ?string $type = null, string $format = 'Y-m-d H:i:s'): int|string|null
    {
        if (is_string($value) && !is_numeric($value)) {
            $parsed = strtotime($value);
            if (false === $parsed) {
                return null;
            }
            $value = $parsed;
        }

        $timestamp = (int) $value;
        if ('1' === $type) {
            return strtotime(date($format, $timestamp)) ?: null;
        }
        if ('2' === $type) {
            return date($format, $timestamp);
        }

        return $timestamp;
    }

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
     * 解析时间范围。
     *
     * 输入可为一个逗号分隔的字符串或两项数组；缺项用开始、结束的默认值补齐，
     * 按各自类型返回时间戳或格式化字符串。
     *
     * @param string|array<array-key, mixed> $time 时间范围
     * @param array<array-key, mixed> $start 开始配置：默认值、类型、格式
     * @param array<array-key, mixed> $end 结束配置：默认值、类型、格式
     * @return array{0: int|string|null, 1: int|string|null} 起止时间
     */
    public static function range(string|array $time, array $start = [], array $end = []): array
    {
        $values = is_string($time) ? explode(',', $time) : array_values($time);

        $startValue = $start[0] ?? 0;
        $startType = $start[1] ?? '1';
        $startFormat = $start[2] ?? 'Y-m-d H:i:s';
        $endValue = $end[0] ?? time();
        $endType = $end[1] ?? '1';
        $endFormat = $end[2] ?? 'Y-m-d H:i:s';

        return [
            self::change(
                trim(Type::toString($values[0] ?? $startValue)),
                is_string($startType) ? $startType : '1',
                is_string($startFormat) && '' !== $startFormat ? $startFormat : 'Y-m-d H:i:s',
            ),
            self::change(
                trim(Type::toString($values[1] ?? $endValue)),
                is_string($endType) ? $endType : '1',
                is_string($endFormat) && '' !== $endFormat ? $endFormat : 'Y-m-d H:i:s',
            ),
        ];
    }

    /**
     * 生成相对时间描述。
     *
     * @param int $timestamp 目标时间戳
     * @param array<array-key, mixed> $options 选项，`now` 可指定当前时间戳
     * @return string 形如「3小时前」「2天后」
     */
    public static function relative(int $timestamp, array $options = []): string
    {
        $now = $options['now'] ?? time();
        $now = is_int($now) ? $now : time();
        $diff = $now - $timestamp;
        $future = $diff < 0;
        $diff = abs($diff);

        if ($diff < 60) {
            return '刚刚';
        }

        $units = [
            [31536000, '年'],
            [2592000, '个月'],
            [86400, '天'],
            [3600, '小时'],
            [60, '分钟'],
        ];
        foreach ($units as [$seconds, $label]) {
            if ($diff >= $seconds) {
                return intdiv($diff, $seconds) . $label . ($future ? '后' : '前');
            }
        }

        return '刚刚';
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
