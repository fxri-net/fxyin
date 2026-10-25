<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * URL 拼装与改写。
 *
 * 只处理查询串层面的拼接，不解析协议与主机，避免对相对地址做多余假设。
 */
final class Url
{
    /**
     * 拼装地址。
     *
     * @param string $path 路径部分
     * @param array<array-key, mixed> $query 查询参数
     * @param string|null $fragment 锚点
     * @return string 完整地址
     */
    public static function build(string $path, array $query = [], ?string $fragment = null): string
    {
        $url = $path;
        if ([] !== $query) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        if (null !== $fragment && '' !== $fragment) {
            $url .= '#' . $fragment;
        }

        return $url;
    }

    /**
     * 解析查询串。
     *
     * @param string $url 地址
     * @return array<array-key, mixed> 查询参数
     */
    public static function query(string $url): array
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || '' === $query) {
            return [];
        }

        $result = [];
        parse_str($query, $result);

        return $result;
    }

    /**
     * 改写查询串，值为 null 表示删除该参数。
     *
     * @param string $url 原地址
     * @param array<array-key, mixed> $query 需要写入的参数
     * @return string 改写后的地址
     */
    public static function withQuery(string $url, array $query): string
    {
        $merged = array_merge(self::query($url), $query);
        $merged = array_filter($merged, static fn (mixed $value): bool => null !== $value);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return self::build(self::strip($url), $merged, is_string($fragment) ? $fragment : null);
    }

    /**
     * 去掉地址中的查询串与锚点。
     *
     * @param string $url 地址
     * @return string 路径部分
     */
    private static function strip(string $url): string
    {
        return substr($url, 0, strcspn($url, '?#'));
    }
}
