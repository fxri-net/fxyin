<?php

declare(strict_types=1);

namespace Fxri\Tests\Text;

use Fxri\Text\Time;
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
        self::assertSame('2026-10-28', Time::change('2026-10-28 12:00:00', '2', 'Y-m-d'));
        self::assertSame(strtotime('2026-10-28'), Time::change('2026-10-28 12:00:00', '1', 'Y-m-d'));
        self::assertSame(1000, Time::change(1000));
        self::assertNull(Time::change('not-a-time'));
    }

    /**
     * 范围缺项用默认值补齐，按各自类型输出。
     */
    public function testRange(): void
    {
        $range = Time::range(
            '2026-10-28 00:00:00,2026-10-28 23:59:59',
            [0, '2', 'Y-m-d'],
            [0, '2', 'Y-m-d'],
        );

        self::assertSame(['2026-10-28', '2026-10-28'], $range);
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
