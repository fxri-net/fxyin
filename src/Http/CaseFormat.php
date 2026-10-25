<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * 输出大小写形态。
 *
 * 替代旧实现里用数字选择原样、小写、大写的行为。
 */
enum CaseFormat
{
    /** 保持原样 */
    case Original;
    /** 转为小写 */
    case Lower;
    /** 转为大写 */
    case Upper;

    /**
     * 按当前形态转换字符串。
     *
     * @param string $value 待转换值
     * @return string 转换结果
     */
    public function apply(string $value): string
    {
        return match ($this) {
            self::Original => $value,
            self::Lower => strtolower($value),
            self::Upper => strtoupper($value),
        };
    }
}
