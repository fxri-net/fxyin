<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 定形的基底模式。
 *
 * 决定结果集的起点：保留输入全部键，或只保留默认值声明的键。
 */
enum BaseMode
{
    /** 以输入为基底，未声明的键原样保留 */
    case Merge;
    /** 只保留声明的键，未声明的键丢弃 */
    case Only;
}
