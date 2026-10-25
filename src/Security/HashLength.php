<?php

declare(strict_types=1);

namespace Fxri\Security;

/**
 * 摘要长度。
 *
 * 取代旧实现用数字选择输出长度，取值即行为。
 */
enum HashLength
{
    /** 16 位，取 32 位结果中间一段 */
    case Short16;

    /** 32 位完整结果 */
    case Full32;
}
