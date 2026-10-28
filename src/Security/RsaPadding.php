<?php

declare(strict_types=1);

namespace Fxri\Security;

/**
 * RSA 填充模式。
 *
 * 只暴露与 openssl 对应的填充方式，每块占用的填充字节数由模式自身给出，
 * 避免调用方按密钥位数硬编码分块大小。
 */
enum RsaPadding
{
    /** PKCS#1 填充 */
    case Pkcs1;

    /** 无填充，由调用方按密钥块大小补齐 */
    case NoPadding;

    /**
     * 返回 openssl 填充常量。
     *
     * @return int 填充常量
     */
    public function constant(): int
    {
        return self::Pkcs1 === $this ? OPENSSL_PKCS1_PADDING : OPENSSL_NO_PADDING;
    }

    /**
     * 返回每块占用的填充字节数。
     *
     * @return int 填充字节数
     */
    public function overhead(): int
    {
        return self::Pkcs1 === $this ? 11 : 0;
    }
}
