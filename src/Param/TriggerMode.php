<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 触发填充的条件。
 */
enum TriggerMode
{
    /** 仅当键不存在 */
    case Absent;
    /** 键不存在或值为空串 */
    case Blank;
}
