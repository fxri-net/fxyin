<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * IP 取信来源。
 *
 * 替代旧实现用数字选择取值方式，默认只信任 TCP 连接的对端地址。
 */
enum IpSource
{
    /** 直连对端地址（REMOTE_ADDR） */
    case Remote;
    /** 转发链地址（X-Forwarded-For），需配合可信代理网段 */
    case Forwarded;
}
