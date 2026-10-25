<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Support\Type;

/**
 * 服务端变量快照。
 *
 * 把超全局变量收敛成只读对象，其余各层只从这里取值，避免直接触碰 $_SERVER。
 */
final class ServerContext
{
    /** @var array<string, mixed> 服务端变量 */
    private readonly array $server;

    /**
     * 初始化。
     *
     * @param array<array-key, mixed> $server 服务端变量
     */
    private function __construct(array $server)
    {
        $normalized = [];

        foreach ($server as $name => $value) {
            $normalized[Type::toString($name)] = $value;
        }

        $this->server = $normalized;
    }

    /**
     * 返回全部变量。
     *
     * @return array<string, mixed> 服务端变量
     */
    public function all(): array
    {
        return $this->server;
    }

    /**
     * 取当前请求的服务端变量。
     *
     * @return self 服务端上下文
     */
    public static function fromGlobals(): self
    {
        return new self($_SERVER);
    }

    /**
     * 从 PSR-7 请求取服务端变量。
     *
     * @param object $request 请求对象
     * @return self 服务端上下文
     */
    public static function fromPsrRequest(object $request): self
    {
        if (!method_exists($request, 'getServerParams')) {
            return new self([]);
        }

        $params = $request->getServerParams();

        return new self(is_array($params) ? $params : []);
    }

    /**
     * 读取单个变量。
     *
     * @param string $name 变量名
     * @param string $default 缺失或空串时的默认值
     * @return string 变量值
     */
    public function get(string $name, string $default = ''): string
    {
        $value = Type::toString($this->server[$name] ?? null);

        return '' === $value ? $default : $value;
    }

    /**
     * 判断变量是否存在。
     *
     * @param string $name 变量名
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->server);
    }

    /**
     * 创建实例。
     *
     * @param array<array-key, mixed> $server 服务端变量
     * @return self 服务端上下文
     */
    public static function make(array $server = []): self
    {
        return new self($server);
    }
}
