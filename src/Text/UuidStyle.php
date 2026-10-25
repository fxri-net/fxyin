<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 标识串的二次加工样式。
 *
 * 统一先去掉横线再加工，保证拼接串与压缩串得到一致结果。
 */
enum UuidStyle
{
    /** 中间 16 位小写 */
    case Lower16;
    /** 全部小写 */
    case Lower32;
    /** 中间 16 位大写 */
    case Upper16;
    /** 全部大写 */
    case Upper32;

    /**
     * 按样式加工标识串。
     *
     * @param string $uuid 原始标识串
     * @return string 加工结果
     */
    public function apply(string $uuid): string
    {
        $compact = str_replace('-', '', $uuid);

        return match ($this) {
            self::Lower16 => strtolower(substr($compact, 8, 16)),
            self::Lower32 => strtolower($compact),
            self::Upper16 => strtoupper(substr($compact, 8, 16)),
            self::Upper32 => strtoupper($compact),
        };
    }
}
