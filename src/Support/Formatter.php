<?php

declare(strict_types=1);

namespace Fxri\Support;

/**
 * 数值与容量格式化。
 *
 * 只做展示层转换，不参与计算，避免调用方各自硬编码单位换算。
 */
final class Formatter
{
    /** 容量单位，按 1024 进制递进 */
    private const SIZE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    /**
     * 格式化数值，带千分位。
     *
     * @param int|float $value 数值
     * @param int $precision 小数位数
     * @return string 格式化结果
     */
    public static function number(int|float $value, int $precision = 2): string
    {
        return number_format((float) $value, $precision);
    }

    /**
     * 格式化字节容量。
     *
     * @param int|float $bytes 字节数
     * @param int $precision 小数位数
     * @return string 形如 1.50 MB
     */
    public static function size(int|float $bytes, int $precision = 2): string
    {
        $value = max(0.0, (float) $bytes);
        $unit = 0;
        $last = count(self::SIZE_UNITS) - 1;
        while ($value >= 1024 && $unit < $last) {
            $value /= 1024;
            ++$unit;
        }

        return self::number($value, $precision) . ' ' . self::SIZE_UNITS[$unit];
    }
}
