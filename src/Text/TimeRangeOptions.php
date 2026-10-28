<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 时间范围配置。
 *
 * 替代 1.x `timeRange()` 的 `[默认值, '1'/'2', 格式]` 位置数组。
 */
final class TimeRangeOptions
{
    /** 缺省默认值 */
    public readonly int|string $default;

    /** 输出形态 */
    public readonly TimeOutput $output;

    /** 时间格式 */
    public readonly string $format;

    /**
     * 初始化。
     *
     * @param int|string $default 缺省默认值
     * @param TimeOutput $output 输出形态
     * @param string $format 时间格式
     */
    public function __construct(
        int|string $default = 0,
        TimeOutput $output = TimeOutput::Timestamp,
        string $format = 'Y-m-d H:i:s',
    ) {
        $this->default = $default;
        $this->output = $output;
        $this->format = $format;
    }

    /**
     * 创建配置。
     *
     * @param int|string $default 缺省默认值
     * @param TimeOutput $output 输出形态
     * @param string $format 时间格式
     * @return self 范围配置
     */
    public static function make(
        int|string $default = 0,
        TimeOutput $output = TimeOutput::Timestamp,
        string $format = 'Y-m-d H:i:s',
    ): self {
        return new self($default, $output, $format);
    }
}
