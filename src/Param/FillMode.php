<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 命中触发条件时的填充方式。
 */
enum FillMode
{
    /** 填入声明的默认值 */
    case Value;
    /** 填入 null，用于只声明键与类型位置的场景 */
    case Null;
}
