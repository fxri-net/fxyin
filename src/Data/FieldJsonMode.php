<?php

declare(strict_types=1);

namespace Fxri\Data;

/**
 * JSON 路径模式。
 *
 * 取代旧 fieldJson 的数字参数，由枚举决定路径是否按 JSON 语法加双引号。
 */
enum FieldJsonMode
{
    /** 键名加双引号 */
    case Quoted;
    /** 路径原样传入 */
    case Raw;
}
