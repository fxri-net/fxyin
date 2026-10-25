<?php

declare(strict_types=1);

namespace Fxri\Data;

/**
 * 文本字段模式。
 *
 * 取代旧 fieldText 的数字参数，把「兜底值怎么用」和「是否转整数」拆成两个维度。
 */
enum FieldTextMode
{
    /** 按兜底值类型推断 */
    case Keep;
    /** 只替换空值 */
    case ReplaceEmpty;
    /** 强制转整数 */
    case CastInt;
}
