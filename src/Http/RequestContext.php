<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Support\Type;

/**
 * 请求上下文。
 *
 * 从超全局变量或 PSR-7 请求构造，构造后只读；调用方不再直接读取 $_SERVER。
 */
final class RequestContext
{
    /** 请求方法 */
    private readonly string $method;

    /** 请求路径 */
    private readonly string $path;

    /** 查询串，不含问号 */
    private readonly string $queryString;

    /** 主机名 */
    private readonly string $host;

    /** 是否安全传输 */
    private readonly bool $secure;

    /** @var array<string, string> 请求头，头名小写 */
    private readonly array $headers;

    /** 请求体 */
    private readonly string $body;

    /**
     * 初始化。
     *
     * @param string $method 请求方法
     * @param string $path 请求路径
     * @param string $queryString 查询串
     * @param string $host 主机名
     * @param bool $secure 是否安全传输
     * @param array<array-key, mixed> $headers 请求头
     * @param string $body 请求体
     */
    private function __construct(string $method, string $path, string $queryString, string $host, bool $secure, array $headers, string $body)
    {
        $this->method = strtoupper($method);
        $this->path = '' === $path ? '/' : $path;
        $this->queryString = ltrim($queryString, '?');
        $this->host = $host;
        $this->secure = $secure;
        $this->headers = self::normalizeHeaders($headers);
        $this->body = $body;
    }

    /**
     * 取请求体。
     *
     * @return string 请求体
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * 取当前请求的上下文。
     *
     * @return self 请求上下文
     */
    public static function fromGlobals(): self
    {
        $body = file_get_contents('php://input');

        return self::fromServer(ServerContext::fromGlobals(), is_string($body) ? $body : '');
    }

    /**
     * 从 PSR-7 请求构造。
     *
     * 缺方法时按缺省值处理，避免对未约定的实现直接报错。
     *
     * @param object $request 请求对象
     * @return self 请求上下文
     */
    public static function fromPsrRequest(object $request): self
    {
        $headers = self::normalizeHeaders(self::readHeaders($request));
        $url = self::readUrl($request);
        $parts = '' === $url ? false : parse_url($url);

        $path = '/';
        $queryString = '';
        $host = '';
        $secure = false;

        if (is_array($parts)) {
            $path = Type::toString($parts['path'] ?? '');
            $queryString = Type::toString($parts['query'] ?? '');
            $host = Type::toString($parts['host'] ?? '');
            $secure = 'https' === strtolower(Type::toString($parts['scheme'] ?? ''));
            if ('' === $path) {
                $path = '/';
            }
        }

        if ('' === $host) {
            $host = $headers['host'] ?? '';
        }

        return new self(self::readMethod($request), $path, $queryString, $host, $secure, $headers, self::readBody($request));
    }

    /**
     * 从服务端变量构造。
     *
     * @param ServerContext $server 服务端上下文
     * @param string $body 请求体
     * @return self 请求上下文
     */
    public static function fromServer(ServerContext $server, string $body = ''): self
    {
        $uri = $server->get('REQUEST_URI');
        $path = $uri;
        $queryString = '';

        $mark = strpos($uri, '?');
        if (false !== $mark) {
            $path = substr($uri, 0, $mark);
            $queryString = substr($uri, $mark + 1);
        }

        if ('' === $path) {
            $path = $server->get('SCRIPT_NAME', '/');
        }

        $host = $server->get('HTTP_HOST', $server->get('SERVER_NAME'));
        $secure = in_array(strtolower($server->get('HTTPS')), ['on', '1'], true);

        return new self($server->get('REQUEST_METHOD', 'GET'), $path, $queryString, $host, $secure, self::headersFromServer($server), $body);
    }

    /**
     * 读取请求头。
     *
     * @param string $name 头名，大小写不敏感
     * @param string $default 缺失时的默认值
     * @return string 头值
     */
    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower(trim($name))] ?? $default;
    }

    /**
     * 返回全部请求头。
     *
     * @return array<string, string> 头名小写 => 头值
     */
    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * 取主机名。
     *
     * @return string 主机名
     */
    public function host(): string
    {
        return $this->host;
    }

    /**
     * 判断是否安全传输。
     *
     * @return bool 是否 https
     */
    public function isSecure(): bool
    {
        return $this->secure;
    }

    /**
     * 创建实例。
     *
     * @param string $method 请求方法
     * @param string $path 请求路径
     * @param array<array-key, mixed> $headers 请求头
     * @param string $body 请求体
     * @param string $host 主机名
     * @param bool $secure 是否安全传输
     * @param string $queryString 查询串
     * @return self 请求上下文
     */
    public static function make(string $method, string $path, array $headers = [], string $body = '', string $host = '', bool $secure = false, string $queryString = ''): self
    {
        return new self($method, $path, $queryString, $host, $secure, $headers, $body);
    }

    /**
     * 取请求方法。
     *
     * @return string 大写方法名
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * 取请求路径。
     *
     * @return string 路径
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * 取查询串。
     *
     * @return string 不含问号的查询串
     */
    public function queryString(): string
    {
        return $this->queryString;
    }

    /**
     * 拼接完整地址。
     *
     * 主机名缺失时只返回路径与查询串，便于在命令行等场景复用。
     *
     * @return string 地址
     */
    public function url(): string
    {
        $target = $this->path;
        if ('' !== $this->queryString) {
            $target .= '?' . $this->queryString;
        }

        if ('' === $this->host) {
            return $target;
        }

        return ($this->secure ? 'https://' : 'http://') . $this->host . $target;
    }

    /**
     * 从服务端变量还原请求头。
     *
     * @param ServerContext $server 服务端上下文
     * @return array<string, string> 请求头
     */
    private static function headersFromServer(ServerContext $server): array
    {
        $headers = [];

        foreach ($server->all() as $name => $value) {
            $key = strtoupper($name);
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($name, 5)))] = Type::toString($value);
                continue;
            }
            if ('CONTENT_TYPE' === $key || 'CONTENT_LENGTH' === $key) {
                $headers[str_replace('_', '-', strtolower($name))] = Type::toString($value);
            }
        }

        return $headers;
    }

    /**
     * 归一请求头。
     *
     * 头名统一小写，多值头用逗号合并，避免同一头名出现多种写法。
     *
     * @param array<array-key, mixed> $headers 原始请求头
     * @return array<string, string> 头名小写 => 头值
     */
    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $key = strtolower(trim(Type::toString($name)));
            if ('' === $key) {
                continue;
            }

            $normalized[$key] = is_array($value)
                ? implode(', ', array_map(Type::toString(...), $value))
                : Type::toString($value);
        }

        return $normalized;
    }

    /**
     * 读取 PSR-7 请求体。
     *
     * @param object $request 请求对象
     * @return string 请求体
     */
    private static function readBody(object $request): string
    {
        if (!method_exists($request, 'getBody')) {
            return '';
        }

        return Type::toString($request->getBody());
    }

    /**
     * 读取 PSR-7 请求头。
     *
     * @param object $request 请求对象
     * @return array<array-key, mixed> 原始请求头
     */
    private static function readHeaders(object $request): array
    {
        if (!method_exists($request, 'getHeaders')) {
            return [];
        }

        $headers = $request->getHeaders();

        return is_array($headers) ? $headers : [];
    }

    /**
     * 读取 PSR-7 请求方法。
     *
     * @param object $request 请求对象
     * @return string 请求方法
     */
    private static function readMethod(object $request): string
    {
        if (!method_exists($request, 'getMethod')) {
            return 'GET';
        }

        return Type::toString($request->getMethod());
    }

    /**
     * 读取 PSR-7 请求地址。
     *
     * @param object $request 请求对象
     * @return string 地址串
     */
    private static function readUrl(object $request): string
    {
        if (!method_exists($request, 'getUri')) {
            return '';
        }

        return Type::toString($request->getUri());
    }
}
