<?php

declare(strict_types=1);

namespace Fxri\Http\HttpClient;

use CurlHandle;
use Fxri\File\Filesystem;
use Fxri\Param\Url;
use Fxri\Support\Type;
use Fxri\Text\Encode;
use InvalidArgumentException;
use RuntimeException;

/**
 * cURL 传输。
 *
 * 提供 URL 级请求入口，承接 1.x 门面 `Service::http()` 的能力；不依赖 PSR-7/PSR-18。
 * 未安装 ext-curl 时调用即抛异常，不静默降级。
 */
final class Curl
{
    /**
     * 下载响应体到文件。
     *
     * 非 2xx 响应抛异常，避免把错误页写成目标文件；父目录自动创建。
     *
     * @param string $url 地址
     * @param string $file 本地文件路径
     * @param array<array-key, mixed> $headers 请求头
     * @param CurlOptions|null $options 传输选项
     * @return string 写入的文件路径
     */
    public static function download(string $url, string $file, array $headers = [], ?CurlOptions $options = null): string
    {
        $response = self::request($url, '', $headers, 'GET', $options);
        if (!$response->isSuccess()) {
            throw new RuntimeException('下载失败：HTTP ' . $response->status());
        }

        Filesystem::write($file, $response->body());

        return $file;
    }

    /**
     * 发送请求并返回正文。
     *
     * @param string $url 地址
     * @param array<array-key, mixed>|string $data 数据
     * @param array<array-key, mixed> $headers 请求头
     * @param string $method 请求方法
     * @param CurlOptions|null $options 传输选项
     * @return string 响应正文
     */
    public static function send(string $url, array|string $data = '', array $headers = [], string $method = 'GET', ?CurlOptions $options = null): string
    {
        return self::request($url, $data, $headers, $method, $options)->body();
    }

    /**
     * 发送请求。
     *
     * GET 与 HEAD 的数组数据拼进查询串，其余方法作为请求体；默认校验证书与主机名、
     * 跟随重定向并优先 IPv4。响应体去掉 UTF-8 BOM。
     *
     * @param string $url 地址
     * @param array<array-key, mixed>|string $data 数据
     * @param array<array-key, mixed> $headers 请求头
     * @param string $method 请求方法
     * @param CurlOptions|null $options 传输选项
     * @return CurlResponse 响应快照
     */
    public static function request(string $url, array|string $data = '', array $headers = [], string $method = 'GET', ?CurlOptions $options = null): CurlResponse
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('未安装 ext-curl，无法发起 URL 级请求');
        }

        $options ??= CurlOptions::make();
        $method = strtoupper(trim($method));
        if ('' === $method) {
            $method = 'GET';
        }

        $target = $url;
        $body = '';

        if ('GET' === $method || 'HEAD' === $method) {
            if (is_array($data) && [] !== $data) {
                $target = Url::withQuery($url, $data);
            }
        } elseif (is_array($data)) {
            $body = Encode::encodeAll($data);
        } else {
            $body = $data;
        }

        if ('' === $target) {
            throw new InvalidArgumentException('请求地址不能为空');
        }

        $handle = curl_init();
        if (false === $handle) {
            throw new RuntimeException('无法初始化 cURL');
        }

        /** @var array<string, string> $responseHeaders 响应头，重定向时重置 */
        $responseHeaders = [];

        curl_setopt($handle, CURLOPT_URL, $target);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, $options->follow);
        curl_setopt($handle, CURLOPT_MAXREDIRS, $options->maxRedirects);
        curl_setopt($handle, CURLOPT_TIMEOUT, $options->timeout);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $options->connectTimeout);

        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $options->verify);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $options->verify ? 2 : 0);

        if ($options->ipv4 && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
            curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }

        $headerLines = self::headerLines($headers);
        if ([] !== $headerLines) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headerLines);
        }

        if ('' !== $body) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $agent = $options->userAgent;
        if (null !== $agent && '' !== $agent) {
            curl_setopt($handle, CURLOPT_USERAGENT, $agent);
        }

        curl_setopt($handle, CURLOPT_HEADERFUNCTION, static function (CurlHandle $curl, string $line) use (&$responseHeaders): int {
            $trimmed = trim($line);
            if ('' === $trimmed) {
                return strlen($line);
            }

            // 重定向会追加新的响应块，状态行出现时丢弃上一块的头
            if (str_starts_with(strtoupper($trimmed), 'HTTP/')) {
                $responseHeaders = [];

                return strlen($line);
            }

            $parts = explode(':', $trimmed, 2);
            if (2 === count($parts)) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return strlen($line);
        });

        $content = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($content)) {
            throw new RuntimeException('HTTP 请求失败：' . ('' === $error ? '未知错误' : $error));
        }

        // 去掉 UTF-8 BOM，避免响应体首部混入不可见字符
        $content = ltrim($content, "\xEF\xBB\xBF");

        return new CurlResponse($status, $responseHeaders, $content);
    }

    /**
     * 归一请求头。
     *
     * 接受 `['Name: value']` 列表与 `['Name' => 'value']` 映射两种写法。
     *
     * @param array<array-key, mixed> $headers 请求头
     * @return list<string> 头行
     */
    private static function headerLines(array $headers): array
    {
        $lines = [];

        foreach ($headers as $name => $value) {
            if (is_int($name)) {
                $line = trim(Type::toString($value));
                if ('' !== $line) {
                    $lines[] = $line;
                }

                continue;
            }

            $lines[] = Type::toString($name) . ': ' . Type::toString($value);
        }

        return $lines;
    }
}
