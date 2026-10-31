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
     * 字符串先解析为时间戳；`Timestamp` 按格式截断后返回时间戳，`Text` 返回格式化字符串，
     * `null` 返回原始时间戳。无法解析时抛 `InvalidArgumentException`。
     *
     * @param int|string $value 时间戳或时间串
     * @param TimeOutput|null $output 输出形态
     * @param string $format 格式
     * @return int|string 转换结果
     */
    public static function change(int|string $value, ?TimeOutput $output = null, string $format = 'Y-m-d H:i:s'): int|string
    {
        $timestamp = self::parse($value);

        if (TimeOutput::Timestamp === $output) {
            $truncated = strtotime(date($format, $timestamp));
            if (false === $truncated) {
                throw new InvalidArgumentException('无法按格式截断时间：' . $format);
            }

            return $truncated;
        }
        if (TimeOutput::Text === $output) {
            return date($format, $timestamp);
        }

        return $timestamp;
    }

    /**
     * 把秒数分解为年、月、天、时、分、秒。
     *
     * 单位链与 1.6.1 一致：一年 12 个月、一个月 30 天；负数按 0 处理。
     *
     * @param int $seconds 秒数
     * @return array{year: int, month: int, day: int, hour: int, minute: int, second: int} 分解结果
     */
    public static function decompose(int $seconds): array
    {
        $seconds = max(0, $seconds);

        $second = $seconds % 60;
        $seconds = intdiv($seconds, 60);
        $minute = $seconds % 60;
        $seconds = intdiv($seconds, 60);
        $hour = $seconds % 24;
        $seconds = intdiv($seconds, 24);
        $day = $seconds % 30;
        $seconds = intdiv($seconds, 30);
        $month = $seconds % 12;

        return [
            'year' => intdiv($seconds, 12),
            'month' => $month,
            'day' => $day,
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
        ];
    }

    /**
     * 拼接时长文本。
     *
     * 按年、月、天、时、分、秒顺序输出；标签留空即关闭该单位，`$max` 指定最高单位，
     * `$before` 为 false 时只保留最高单位。对应 1.6.1 `timeFormat()` 的 switch/show/max/before。
     *
     * @param int $seconds 秒数
     * @param array<string, string> $labels 单位标签覆盖，键为 TimeUnit 的值
     * @param bool $zero 是否输出 0 值单位
     * @param TimeUnit|null $max 最高单位，null 表示年
     * @param bool $before 是否输出最高单位以下的更细单位
     * @return string 时长文本
     */
    public static function duration(int $seconds, array $labels = [], bool $zero = false, ?TimeUnit $max = null, bool $before = true): string
    {
        $maxUnit = $max ?? TimeUnit::Year;
        $order = [TimeUnit::Year, TimeUnit::Month, TimeUnit::Day, TimeUnit::Hour, TimeUnit::Minute, TimeUnit::Second];
        $chain = [
            [TimeUnit::Second, 60],
            [TimeUnit::Minute, 60],
            [TimeUnit::Hour, 24],
            [TimeUnit::Day, 30],
            [TimeUnit::Month, 12],
            [TimeUnit::Year, 0],
        ];

        // 从秒开始逐级向上取余，最高单位直接取剩余值，与 1.x 把最高单位除数置 0 的做法一致
        $parts = [];
        $remaining = max(0, $seconds);
        foreach ($chain as [$unit, $divisor]) {
            if ($unit === $maxUnit) {
                $parts[$unit->value] = $remaining;

                break;
            }

            $parts[$unit->value] = 0 === $divisor ? $remaining : $remaining % $divisor;
            if (0 === $divisor) {
                break;
            }

            $remaining = intdiv($remaining, $divisor);
        }

        $result = '';
        foreach ($order as $unit) {
            $value = $parts[$unit->value] ?? null;
            if (null === $value || (!$before && $unit !== $maxUnit)) {
                continue;
            }

            $label = $labels[$unit->value] ?? $unit->label();
            if ('' === $label || (!$zero && 0 === $value)) {
                continue;
            }

            $result .= $value . $label;
        }

        return $result;
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
     * 把毫秒时间戳格式化为带毫秒的时间串。
     *
     * @param int $milliseconds 毫秒时间戳
     * @return string 形如 `Y-m-d H:i:s.mmm`
     */
    public static function milliFormat(int $milliseconds): string
    {
        $seconds = intdiv($milliseconds, 1000);
        $remainder = $milliseconds % 1000;

        return date('Y-m-d H:i:s', $seconds) . '.' . str_pad((string) $remainder, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 解析毫秒时间。
     *
     * 接受 13 位毫秒串与 `Y-m-d H:i:s.mmm` 时间串；其它形态抛 `InvalidArgumentException`。
     *
     * @param int|string $value 毫秒串或时间串
     * @return int 毫秒时间戳
     */
    public static function milliParse(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $value = trim($value);
        if (13 === strlen($value) && ctype_digit($value)) {
            return (int) $value;
        }

        $parts = explode('.', $value, 2);
        $seconds = strtotime($parts[0]);
        if (false === $seconds) {
            throw new InvalidArgumentException('无法解析的毫秒时间：' . $value);
        }

        $milliseconds = isset($parts[1]) ? (int) str_pad(substr($parts[1], 0, 3), 3, '0', STR_PAD_RIGHT) : 0;

        return $seconds * 1000 + $milliseconds;
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
     * 输入可为一个逗号分隔的字符串或两项数组；缺项用起止配置补齐，
     * 按各自输出形态返回时间戳或格式化字符串。
     *
     * @param string|array<array-key, mixed> $time 时间范围
     * @param TimeRangeOptions|null $start 开始配置，null 表示默认值 0、时间戳输出
     * @param TimeRangeOptions|null $end 结束配置，null 表示当前时间、时间戳输出
     * @return array{0: int|string, 1: int|string} 起止时间
     */
    public static function range(string|array $time, ?TimeRangeOptions $start = null, ?TimeRangeOptions $end = null): array
    {
        $values = is_string($time) ? explode(',', $time) : array_values($time);
        $startOptions = $start ?? TimeRangeOptions::make(0);
        $endOptions = $end ?? TimeRangeOptions::make(time());

        // 空串与缺项一样视为未提供，交给起止配置的默认值补齐
        $begin = trim(Type::toString($values[0] ?? ''));
        $finish = trim(Type::toString($values[1] ?? ''));

        return [
            self::change('' === $begin ? $startOptions->default : $begin, $startOptions->output, $startOptions->format),
            self::change('' === $finish ? $endOptions->default : $finish, $endOptions->output, $endOptions->format),
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
        if (!is_int($now)) {
            throw new InvalidArgumentException('now 必须为整数时间戳');
        }
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
