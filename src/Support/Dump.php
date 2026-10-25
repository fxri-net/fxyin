<?php

declare(strict_types=1);

namespace Fxri\Support;

/**
 * 调试输出。
 *
 * 渲染与写入分开：需要把结果交给日志或接口时取 render，命令行排错时用 write。
 */
final class Dump
{
    /**
     * 渲染调试信息。
     *
     * @param mixed ...$values 待观察的值
     * @return string CLI 下为纯文本，Web 下为转义后的 pre 片段
     */
    public static function render(mixed ...$values): string
    {
        $chunks = [];
        foreach ($values as $value) {
            ob_start();
            var_dump($value);
            $chunks[] = (string) ob_get_clean();
        }

        $text = preg_replace('/\]\s*=>\s*\n\s+/m', '] => ', implode(PHP_EOL, $chunks)) ?? '';

        return 'cli' === PHP_SAPI
            ? $text
            : '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
    }

    /**
     * 直接输出调试信息。
     *
     * @param mixed ...$values 待观察的值
     * @return void
     */
    public static function write(mixed ...$values): void
    {
        echo self::render(...$values), PHP_EOL;
    }
}
