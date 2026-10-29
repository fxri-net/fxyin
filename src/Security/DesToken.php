<?php

declare(strict_types=1);

namespace Fxri\Security;

use Fxri\Support\Random;
use Fxri\Support\RandomCharset;
use Fxri\Support\Type;
use InvalidArgumentException;
use RuntimeException;

/**
 * 1.x 令牌读写。
 *
 * 只用于读写旧版本签发的令牌：数据以逗号包裹、补随机字符到 2 的幂后走 DES-ECB，
 * 最终转为十六进制串。新数据请使用 `Token`。
 */
final class DesToken
{
    /** 最小填充长度 */
    private const MIN_LENGTH = 32;

    /**
     * 解码旧令牌。
     *
     * 格式非法或解密失败时返回 `null`，不抛异常。
     *
     * @param string $token 十六进制令牌
     * @param string $password 旧口令，1.x 默认为 `00000000`
     * @return string|null 原始逗号串，失败返回 null
     */
    public static function decode(string $token, #[\SensitiveParameter] string $password): ?string
    {
        if ('' === $token || 0 !== strlen($token) % 2 || 1 !== preg_match('/^[0-9a-fA-F]+$/', $token)) {
            return null;
        }

        $raw = hex2bin($token);
        if (false === $raw) {
            return null;
        }

        try {
            $plain = DesCipher::decrypt($raw, $password);
        } catch (InvalidArgumentException|RuntimeException) {
            return null;
        }

        $parts = explode(',', $plain);
        array_shift($parts);
        array_pop($parts);

        return implode(',', $parts);
    }

    /**
     * 编码旧令牌。
     *
     * @param string|array<array-key, mixed> $data 原始数据，数组按逗号拼接
     * @param string $password 旧口令
     * @return string 十六进制令牌
     */
    public static function encode(string|array $data, #[\SensitiveParameter] string $password): string
    {
        if (is_array($data)) {
            $data = implode(',', array_map(Type::toString(...), array_values($data)));
        }

        if ('' === $data) {
            throw new InvalidArgumentException('令牌数据不能为空');
        }

        $payload = ',' . $data . ',';
        $length = strlen($payload);
        $target = self::MIN_LENGTH;
        while ($target < $length) {
            $target *= 2;
        }

        $padding = intdiv($target - $length, 2);
        $pad = Random::string(max(1, $padding), RandomCharset::Alnum);
        $payload = str_pad($payload, $target, $pad, STR_PAD_BOTH);

        return bin2hex(DesCipher::encrypt($payload, $password));
    }
}
