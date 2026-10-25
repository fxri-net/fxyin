<?php

declare(strict_types=1);

namespace Fxri\Security;

/**
 * 摘要与口令散列。
 *
 * md5 仅保留用于兼容历史数据，新场景请用 password 系列方法。
 */
final class Hash
{
    /**
     * 比较两段字符串。
     *
     * 耗时与内容无关，可用于校验签名与令牌。
     *
     * @param string $known 已知值
     * @param string $given 待比较值
     * @return bool 是否一致
     */
    public static function equals(#[\SensitiveParameter] string $known, #[\SensitiveParameter] string $given): bool
    {
        return hash_equals($known, $given);
    }

    /**
     * 计算 MD5 摘要。
     *
     * MD5 已不适合安全场景，只用于兼容历史数据。
     *
     * @param string $data 原始内容
     * @param HashLength $length 输出长度
     * @return string 十六进制摘要
     */
    public static function md5(string $data, HashLength $length = HashLength::Full32): string
    {
        $hash = md5($data);

        return HashLength::Short16 === $length ? substr($hash, 8, 16) : $hash;
    }

    /**
     * 生成口令散列。
     *
     * @param string $password 明文口令
     * @return string 可直接存储的散列
     */
    public static function password(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * 校验口令。
     *
     * @param string $password 明文口令
     * @param string $hash 存储的散列
     * @return bool 是否匹配
     */
    public static function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
