<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 编码转换方向。
 *
 * 替代 1.x `convert()` 的字符串类型 `hexstr` / `strhex`。
 */
enum EncodeConvert
{
    /** 字符串转十六进制 */
    case ToHex;

    /** 十六进制转字符串 */
    case FromHex;
}
