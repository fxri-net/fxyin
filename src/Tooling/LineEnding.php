<?php

declare(strict_types=1);

namespace Fxri\Tooling;

/**
 * 行尾归一。
 *
 * 生成物与文件比对前统一按 LF 比较，避免 Windows 检出的 CRLF 造成假不一致。
 *
 * @internal 实现细节，不对外承诺
 */
final class LineEnding
{
    /**
     * 归一为 LF。
     *
     * @param string $contents 文本内容
     * @return string 归一后的内容
     */
    public static function normalize(string $contents): string
    {
        return str_replace(["\r\n", "\r"], "\n", $contents);
    }
}
