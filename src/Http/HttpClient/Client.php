<?php

declare(strict_types=1);

namespace Fxri\Http\HttpClient;

use InvalidArgumentException;
use RuntimeException;

/**
 * PSR-18 客户端包装。
 *
 * 接口名只以字符串常量声明，未安装 PSR-7/PSR-18 实现包时本类仍可安全加载。
 */
final class Client
{
    /** PSR-18 客户端接口 */
    public const CLIENT_INTERFACE = 'Psr\Http\Client\ClientInterface';

    /** PSR-7 请求接口 */
    public const REQUEST_INTERFACE = 'Psr\Http\Message\RequestInterface';

    /** PSR-7 响应接口 */
    public const RESPONSE_INTERFACE = 'Psr\Http\Message\ResponseInterface';

    /**
     * 初始化。
     *
     * @param object $client PSR-18 客户端
     */
    private function __construct(private readonly object $client)
    {
    }

    /**
     * 取底层客户端。
     *
     * @return object PSR-18 客户端
     */
    public function client(): object
    {
        return $this->client;
    }

    /**
     * 创建实例。
     *
     * @param object $client PSR-18 客户端
     * @return self 客户端包装
     */
    public static function make(object $client): self
    {
        if (!is_a($client, self::CLIENT_INTERFACE)) {
            throw new InvalidArgumentException('客户端未实现 ' . self::CLIENT_INTERFACE);
        }

        return new self($client);
    }

    /**
     * 发送请求。
     *
     * @param object $request PSR-7 请求
     * @return object PSR-7 响应
     */
    public function send(object $request): object
    {
        if (!is_a($request, self::REQUEST_INTERFACE)) {
            throw new InvalidArgumentException('请求未实现 ' . self::REQUEST_INTERFACE);
        }

        if (!method_exists($this->client, 'sendRequest')) {
            throw new RuntimeException('客户端缺少 sendRequest 方法');
        }

        $response = $this->client->sendRequest($request);

        if (!is_object($response) || !is_a($response, self::RESPONSE_INTERFACE)) {
            throw new RuntimeException('响应未实现 ' . self::RESPONSE_INTERFACE);
        }

        return $response;
    }

    /**
     * 替换底层客户端。
     *
     * @param object $client PSR-18 客户端
     * @return self 新的客户端包装
     */
    public function withClient(object $client): self
    {
        return self::make($client);
    }
}
