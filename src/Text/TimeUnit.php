<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 时长单位。
 *
 * 按年、月、天、时、分、秒表达最高单位与标签，替代 1.x 的字符串单位开关。
 */
enum TimeUnit: string
{
    /** 年 */
    case Year = 'year';

    /** 月 */
    case Month = 'month';

    /** 天 */
    case Day = 'day';

    /** 时 */
    case Hour = 'hour';

    /** 分 */
    case Minute = 'minute';

    /** 秒 */
    case Second = 'second';

    /**
     * 返回默认中文标签。
     *
     * @return string 单位标签
     */
    public function label(): string
    {
        return match ($this) {
            self::Year => '年',
            self::Month => '个月',
            self::Day => '天',
            self::Hour => '小时',
            self::Minute => '分钟',
            self::Second => '秒',
        };
    }
}
