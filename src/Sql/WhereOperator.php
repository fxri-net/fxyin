<?php

declare(strict_types=1);

namespace Fxri\Sql;

/**
 * 条件比较方式。
 *
 * 替代旧实现里用字符串方法名区分条件的写法，取值即语义。
 */
enum WhereOperator
{
    /** 等于 */
    case Eq;
    /** 不等于 */
    case Ne;
    /** 大于 */
    case Gt;
    /** 大于等于 */
    case Gte;
    /** 小于 */
    case Lt;
    /** 小于等于 */
    case Lte;
    /** 模糊匹配 */
    case Like;
    /** 集合包含 */
    case In;
    /** 不为空 */
    case NotNull;
    /** 为空 */
    case Null;
    /** 区间 */
    case Between;
}
