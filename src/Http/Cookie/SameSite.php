<?php

declare(strict_types=1);

namespace Fxri\Http\Cookie;

/**
 * Cookie 跨站策略。
 *
 * 取值与 setcookie 的 samesite 参数一致，避免再做字符串拼接。
 */
enum SameSite: string
{
    /** 仅同站请求发送 */
    case Strict = 'Strict';
    /** 同站请求与顶层跳转发送 */
    case Lax = 'Lax';
    /** 跨站请求也发送，需同时开启 secure */
    case None = 'None';
}
