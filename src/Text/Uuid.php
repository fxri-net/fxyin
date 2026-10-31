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
     * 格式化现有标识串。
     *
     * 去掉横线、转大写后按 8-4-4-4-12 重新分段，对应 1.6.1 模式 1 对现有 UUID 的处理；
     * `$style` 可再压缩或调整大小写。
     *
     * @param string $value 现有标识串
     * @param UuidStyle|null $style 二次加工样式
     * @return string 格式化结果
     */
    public static function format(string $value, ?UuidStyle $style = null): string
    {
        $uuid = self::dash(strtoupper(str_replace('-', '', trim($value))));

        return $style instanceof UuidStyle ? $style->apply($uuid) : $uuid;
    }

    /**
     * 生成标识串。
     *
     * 传 seed 时由 md5 派生，不传时为随机 32 位十六进制；Md5Upper 与 Upper32 只决定大小写与是否带横线。
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
