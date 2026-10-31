<?php

declare(strict_types=1);

namespace Fxri\Tests\Text;

use Fxri\Text\Time;
use Fxri\Text\TimeOutput;
use Fxri\Text\TimeRangeOptions;
use Fxri\Text\TimeUnit;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 时间转换测试：格式化、范围与相对描述。
 */
final class TimeTest extends TestCase
{
    /**
     * 类型 2 输出格式化字符串，类型 1 输出截断后的时间戳。
     */
    public function testChange(): void
    {
        self::assertSame('2026-10-28', Time::change('2026-10-28 12:00:00', TimeOutput::Text, 'Y-m-d'));
        self::assertSame(strtotime('2026-10-28'), Time::change('2026-10-28 12:00:00', TimeOutput::Timestamp, 'Y-m-d'));
        self::assertSame(1000, Time::change(1000));
        self::assertSame('1970-01-01', Time::change(0, TimeOutput::Text, 'Y-m-d'));
    }

    /**
     * 无法解析的时间串按统一口径抛异常，而不是返回 null。
     */
    public function testChangeRejectsInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Time::change('not-a-time');
    }

    /**
     * 范围缺项用默认值补齐，按各自类型输出。
     */
    public function testRange(): void
    {
        $range = Time::range(
            '2026-10-28 00:00:00,2026-10-28 23:59:59',
            TimeRangeOptions::make(0, TimeOutput::Text, 'Y-m-d'),
            TimeRangeOptions::make(0, TimeOutput::Text, 'Y-m-d'),
        );

        self::assertSame(['2026-10-28', '2026-10-28'], $range);

        // 逗号串的空项与缺项同义，交给起止配置补齐
        $fallback = Time::range(
            '2026-10-28,',
            TimeRangeOptions::make('2026-10-28', TimeOutput::Text, 'Y-m-d'),
            TimeRangeOptions::make('2026-10-28', TimeOutput::Text, 'Y-m-d'),
        );

        self::assertSame(['2026-10-28', '2026-10-28'], $fallback);
    }

    /**
     * `now` 必须是整数时间戳，类型不符时不做静默兜底。
     */
    public function testRelativeRejectsNonIntNow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Time::relative(1000, ['now' => '4600']);
    }

    /**
     * 时长按 30 天/月、12 月/年分解。
     */
    public function testDecompose(): void
    {
        $seconds = (360 + 60 + 3) * 86400 + 4 * 3600 + 5 * 60 + 6;

        self::assertSame(
            ['year' => 1, 'month' => 2, 'day' => 3, 'hour' => 4, 'minute' => 5, 'second' => 6],
            Time::decompose($seconds),
        );
        self::assertSame(
            ['year' => 0, 'month' => 0, 'day' => 0, 'hour' => 0, 'minute' => 0, 'second' => 0],
            Time::decompose(-10),
        );
    }

    /**
     * 时长文本按标签、零值与最高单位组合。
     */
    public function testDuration(): void
    {
        $seconds = 30 * 86400 + 2 * 3600 + 3 * 60 + 4;

        self::assertSame('1个月2小时3分钟4秒', Time::duration($seconds));
        self::assertSame('0年1个月0天2小时3分钟4秒', Time::duration($seconds, zero: true));
        self::assertSame('30天2小时3分钟4秒', Time::duration($seconds, max: TimeUnit::Day));
        self::assertSame('1个月', Time::duration($seconds, max: TimeUnit::Month, before: false));
        self::assertSame(
            '1个月2小时',
            Time::duration($seconds, ['year' => '', 'month' => '个月', 'day' => '', 'hour' => '小时', 'minute' => '', 'second' => '']),
        );
    }

    /**
     * 毫秒时间串与毫秒时间戳可双向转换。
     */
    public function testMilliConvert(): void
    {
        $milliseconds = 1700000000123;

        self::assertSame(date('Y-m-d H:i:s', intdiv($milliseconds, 1000)) . '.123', Time::milliFormat($milliseconds));
        self::assertSame($milliseconds, Time::milliParse('1700000000123'));
        self::assertSame(strtotime('2026-10-28 12:00:00') * 1000 + 5, Time::milliParse('2026-10-28 12:00:00.005'));
        self::assertSame($milliseconds, Time::milliParse($milliseconds));
    }

    /**
     * 无法解析的毫秒串被拒绝。
     */
    public function testMilliParseInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Time::milliParse('not-a-time');
    }

    /**
     * 相对时间按差值选择单位，未来使用「后」。
     */
    public function testRelative(): void
    {
        self::assertSame('刚刚', Time::relative(1000, ['now' => 1059]));
        self::assertSame('1分钟前', Time::relative(1000, ['now' => 1060]));
        self::assertSame('1小时前', Time::relative(1000, ['now' => 4600]));
        self::assertSame('1天前', Time::relative(1000, ['now' => 87400]));
        self::assertSame('2小时后', Time::relative(8200, ['now' => 1000]));
    }
}
