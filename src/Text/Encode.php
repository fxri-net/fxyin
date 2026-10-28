<?php

declare(strict_types=1);

namespace Fxri\Text;

use InvalidArgumentException;

/**
 * 编解码工具。
 *
 * 只封装标准编解码，不自造格式；解码失败一律抛异常而不是返回 false。
 */
final class Encode
{
    /**
     * Base64 解码。
     *
     * @param string $value 待解码内容
     * @param bool $strict 是否拒绝非法字符
     * @return string 解码结果
     */
    public static function base64Decode(string $value, bool $strict = false): string
    {
        $decoded = base64_decode($value, $strict);
        if (false === $decoded) {
            throw new InvalidArgumentException('Base64 解码失败');
        }

        return $decoded;
    }

    /**
     * Base64 编码。
     *
     * @param string $value 待编码内容
     * @return string 编码结果
     */
    public static function base64Encode(string $value): string
    {
        return base64_encode($value);
    }

    /**
     * 按方向转换字符串。
     *
     * `ToHex` 把字符串转为十六进制，`FromHex` 把十六进制还原为字符串。
     *
     * @param string $value 待转换内容
     * @param EncodeConvert $convert 转换方向
     * @return string 转换结果
     */
    public static function convert(string $value, EncodeConvert $convert): string
    {
        return match ($convert) {
            EncodeConvert::ToHex => bin2hex($value),
            EncodeConvert::FromHex => self::fromHex($value),
        };
    }

    /**
     * 解码嵌套查询串。
     *
     * @param string $query 查询串
     * @return array<array-key, mixed> 解码结果
     */
    public static function decodeAll(string $query): array
    {
        $result = [];
        parse_str($query, $result);

        return $result;
    }

    /**
     * 编码嵌套数组为查询串。
     *
     * @param array<array-key, mixed> $data 待编码数据
     * @return string 查询串
     */
    public static function encodeAll(array $data): string
    {
        return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * 十六进制解码。
     *
     * @param string $value 待解码内容
     * @return string 解码结果
     */
    public static function hexDecode(string $value): string
    {
        $decoded = hex2bin($value);
        if (false === $decoded) {
            throw new InvalidArgumentException('十六进制解码失败');
        }

        return $decoded;
    }

    /**
     * 十六进制编码。
     *
     * @param string $value 待编码内容
     * @param bool $upper 是否输出大写
     * @return string 编码结果
     */
    public static function hexEncode(string $value, bool $upper = false): string
    {
        $encoded = bin2hex($value);

        return $upper ? strtoupper($encoded) : $encoded;
    }

    /**
     * HTML 实体解码。
     *
     * @param string $value 待解码内容
     * @return string 解码结果
     */
    public static function htmlDecode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * HTML 实体编码。
     *
     * @param string $value 待编码内容
     * @param bool $doubleEncode 是否重复编码已有实体
     * @return string 编码结果
     */
    public static function htmlEncode(string $value, bool $doubleEncode = true): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', $doubleEncode);
    }

    /**
     * 取单个字符的首字母。
     *
     * 英文字母返回大写；中文按 GBK 编码区间映射到 A-Z；无法识别时返回原字符。
     *
     * @param string $value 字符
     * @param string $inCharset 输入字符集
     * @param string $outCharset 输出字符集
     * @return string 首字母
     */
    public static function letter(string $value, string $inCharset = 'UTF-8', string $outCharset = 'GB2312'): string
    {
        if ('' === $value) {
            return '';
        }

        $first = mb_substr($value, 0, 1, 'UTF-8');
        if (1 === preg_match('/^[A-Za-z]$/', $first)) {
            return strtoupper($first);
        }

        $converted = @iconv($inCharset, $outCharset, $first);
        if (false === $converted || '' === $converted) {
            $converted = mb_convert_encoding($first, $outCharset, $inCharset);
        }
        if (!is_string($converted) || strlen($converted) < 2) {
            return $value;
        }

        $code = ord($converted[0]) * 256 + ord($converted[1]) - 65536;
        $ranges = [
            [-20319, -20284, 'A'],
            [-20283, -19776, 'B'],
            [-19775, -19219, 'C'],
            [-19218, -18711, 'D'],
            [-18710, -18527, 'E'],
            [-18526, -18240, 'F'],
            [-18239, -17923, 'G'],
            [-17922, -17418, 'H'],
            [-17417, -16475, 'J'],
            [-16474, -16213, 'K'],
            [-16212, -15641, 'L'],
            [-15640, -15166, 'M'],
            [-15165, -14923, 'N'],
            [-14922, -14915, 'O'],
            [-14914, -14631, 'P'],
            [-14630, -14150, 'Q'],
            [-14149, -14091, 'R'],
            [-14090, -13319, 'S'],
            [-13318, -12839, 'T'],
            [-12838, -12557, 'W'],
            [-12556, -11848, 'X'],
            [-11847, -11056, 'Y'],
            [-11055, -10247, 'Z'],
        ];
        foreach ($ranges as [$start, $end, $letter]) {
            if ($code >= $start && $code <= $end) {
                return $letter;
            }
        }

        return $value;
    }

    /**
     * 取字符串的首字母组。
     *
     * @param string $value 字符串
     * @param string $inCharset 输入字符集
     * @param string $outCharset 输出字符集
     * @return string 首字母拼接结果
     */
    public static function letters(string $value, string $inCharset = 'UTF-8', string $outCharset = 'GB2312'): string
    {
        $letters = [];
        $length = mb_strlen($value, 'UTF-8');
        for ($index = 0; $index < $length; ++$index) {
            $letters[] = self::letter(mb_substr($value, $index, 1, 'UTF-8'), $inCharset, $outCharset);
        }

        return implode('', $letters);
    }

    /**
     * 解析查询串。
     *
     * @param string $query 查询串
     * @return array<array-key, mixed> 解析结果
     */
    public static function queryDecode(string $query): array
    {
        $result = [];
        parse_str($query, $result);

        return $result;
    }

    /**
     * 生成查询串，按 RFC 3986 编码。
     *
     * @param array<array-key, mixed> $data 待编码数据
     * @return string 查询串
     */
    public static function queryEncode(array $data): string
    {
        return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * URL 解码。
     *
     * @param string $value 待解码内容
     * @return string 解码结果
     */
    public static function urlDecode(string $value): string
    {
        return rawurldecode($value);
    }

    /**
     * URL 编码。
     *
     * @param string $value 待编码内容
     * @return string 编码结果
     */
    public static function urlEncode(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * 校验后解码十六进制。
     *
     * 长度为奇数或含非十六进制字符时按解码失败处理，避免底层函数告警。
     *
     * @param string $value 待解码内容
     * @return string 解码结果
     */
    private static function fromHex(string $value): string
    {
        if (0 !== strlen($value) % 2 || 1 !== preg_match('/^[0-9a-fA-F]*$/', $value)) {
            throw new InvalidArgumentException('十六进制解码失败');
        }

        return self::hexDecode($value);
    }
}
