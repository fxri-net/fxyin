<?php

declare(strict_types=1);

namespace Fxri\Text;

use Fxri\Support\Type;
use InvalidArgumentException;

/**
 * IP 地址转换。
 *
 * 整数与二进制形态都按网络字节序处理，便于入库与比较。
 */
final class Ip
{
    /**
     * 二进制转可读地址。
     *
     * @param string $binary 二进制形态
     * @return string 可读地址
     */
    public static function fromBinary(string $binary): string
    {
        $ip = inet_ntop($binary);
        if (false === $ip) {
            throw new InvalidArgumentException('二进制 IP 长度非法');
        }

        return $ip;
    }

    /**
     * 整数转可读地址。
     *
     * @param int $long 整数形态
     * @return string 可读地址
     */
    public static function fromLong(int $long): string
    {
        // 按网络字节序打包，避免 long2ip 对不同整数范围的形态差异
        return self::fromBinary(pack('N', $long));
    }

    /**
     * 可读地址转二进制。
     *
     * @param string $ip 可读地址
     * @return string 二进制形态
     */
    public static function toBinary(string $ip): string
    {
        $binary = inet_pton($ip);
        if (false === $binary) {
            throw new InvalidArgumentException('不是合法的 IP 地址：' . $ip);
        }

        return $binary;
    }

    /**
     * 可读地址转整数。
     *
     * @param string $ip 可读地址
     * @return int 整数形态
     */
    public static function toLong(string $ip): int
    {
        if (!Type::isIpv4($ip)) {
            throw new InvalidArgumentException('不是合法的 IPv4 地址：' . $ip);
        }

        // 经 sprintf 转成无符号十进制，避免高位地址在 32 位置机器上变成负数
        return (int) sprintf('%u', (int) ip2long($ip));
    }
}
