<?php

declare(strict_types=1);

namespace Fxri\Sql;

/**
 * 字符串比较的大小写策略。
 *
 * 替代旧配置里的取反位旁的大小写位，默认交给字段自身的排序规则。
 */
enum Collation
{
    /** 按字段排序规则比较 */
    case CaseInsensitive;
    /** 以二进制比较，大小写敏感 */
    case CaseSensitive;
}
