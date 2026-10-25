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
}
