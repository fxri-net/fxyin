<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 标识串生成。
 *
 * 无种子时使用密码学安全随机源；给种子时退化为 MD5 派生，仅用于需要可复现的场景。
 */
final class Uuid
{
    /**
     * 生成标识串。
     *
     * @param UuidMode $mode 形态
     * @param string|null $seed 派生种子，null 表示随机
     * @param UuidStyle|null $style 二次加工样式
     * @return string 标识串
     */
    public static function make(UuidMode $mode = UuidMode::Standard, ?string $seed = null, ?UuidStyle $style = null): string
    {
        $hex = null === $seed ? bin2hex(random_bytes(16)) : md5($seed);

        $uuid = match ($mode) {
            UuidMode::Standard => self::dash($hex),
            UuidMode::Md5Upper, UuidMode::Upper32 => strtoupper($hex),
        };

        return $style instanceof UuidStyle ? $style->apply($uuid) : $uuid;
    }

    /**
     * 把 32 位十六进制串按下发格式插入横线。
     *
     * @param string $hex 32 位十六进制串
     * @return string 带横线的标识串
     */
    private static function dash(string $hex): string
    {
        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ]);
    }
}
