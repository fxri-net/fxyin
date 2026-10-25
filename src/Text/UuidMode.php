<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 标识串形态。
 *
 * 替代旧实现里以数字区分的 uuid 模式。
 */
enum UuidMode
{
    /** 标准带横线的 36 位串 */
    case Standard;
    /** 大写 32 位串 */
    case Upper32;
    /** 由种子派生的 MD5 大写串 */
    case Md5Upper;
}
