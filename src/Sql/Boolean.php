<?php

declare(strict_types=1);

namespace Fxri\Sql;

/**
 * 条件连接方式。
 *
 * 取值直接是 SQL 关键字，替代旧配置里的逻辑位字符串。
 */
enum Boolean: string
{
    /** 全部条件都要满足 */
    case And = 'AND';
    /** 满足任一条件即可 */
    case Or = 'OR';
}
