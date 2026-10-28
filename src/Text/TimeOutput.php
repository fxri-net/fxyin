<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 时间输出形态。
 *
 * 替代 1.x `timeChange()` 的字符串类型 `'1'` / `'2'`。
 */
enum TimeOutput
{
    /** 按格式截断后返回时间戳 */
    case Timestamp;

    /** 按格式返回字符串 */
    case Text;
}
