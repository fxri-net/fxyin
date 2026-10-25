<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\RequestContext;
use Fxri\Http\ServerContext;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 请求上下文测试：覆盖服务端变量与 PSR-7 两条构造路径。
 */
final class RequestContextTest extends TestCase
{
    /**
     * 从服务端变量还原请求。
     */
    public function testFromServer(): void
    {
        $server = ServerContext::make([
            'REQUEST_METHOD' => 'post',
            'REQUEST_URI' => '/api/user?id=7',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
            'HTTP_X_TRACE' => 'abc',
            'CONTENT_TYPE' => 'application/json',
        ]);

        $request = RequestContext::fromServer($server, '{"a":1}');

        self::assertSame('POST', $request->method());
        self::assertSame('/api/user', $request->path());
        self::assertSame('id=7', $request->queryString());
        self::assertSame('example.com', $request->host());
        self::assertTrue($request->isSecure());
        self::assertSame('abc', $request->header('X-Trace'));
        self::assertSame('application/json', $request->header('content-type'));
        self::assertSame('', $request->header('missing'));
        self::assertSame('fallback', $request->header('missing', 'fallback'));
        self::assertSame('{"a":1}', $request->body());
        self::assertSame('https://example.com/api/user?id=7', $request->url());
    }

    /**
     * 空地址回落到脚本路径。
     */
    public function testEmptyUriFallsBack(): void
    {
        $request = RequestContext::fromServer(ServerContext::make(['SCRIPT_NAME' => '/index.php']));

        self::assertSame('GET', $request->method());
        self::assertSame('/index.php', $request->path());
        self::assertSame('', $request->host());
        self::assertSame('/index.php', $request->url());
    }

    /**
     * 头名归一为小写，多值头合并。
     */
    public function testHeadersNormalized(): void
    {
        $request = RequestContext::make('GET', '/', ['Accept' => ['text/html', 'application/json'], 'X-Empty' => null]);

        self::assertSame('text/html, application/json', $request->header('accept'));
        self::assertSame('', $request->header('x-empty'));
        self::assertSame(['accept' => 'text/html, application/json', 'x-empty' => ''], $request->headers());
    }

    /**
     * PSR-7 请求按接口方法取值。
     */
    public function testFromPsrRequest(): void
    {
        $request = new class () {
            public function getMethod(): string
            {
                return 'put';
            }

            public function getUri(): string
            {
                return 'https://api.example.com/v1/item/9?tab=log#top';
            }

            /**
             * @return array<string, array<string>> 请求头
             */
            public function getHeaders(): array
            {
                return ['Host' => ['api.example.com'], 'Content-Type' => ['application/json']];
            }

            public function getBody(): string
            {
                return '{}';
            }
        };

        $context = RequestContext::fromPsrRequest($request);

        self::assertSame('PUT', $context->method());
        self::assertSame('/v1/item/9', $context->path());
        self::assertSame('tab=log', $context->queryString());
        self::assertSame('api.example.com', $context->host());
        self::assertTrue($context->isSecure());
        self::assertSame('{}', $context->body());
        self::assertSame('application/json', $context->header('content-type'));
    }

    /**
     * 地址缺主机时回落到 Host 头。
     */
    public function testPsrHostFallback(): void
    {
        $request = new class () {
            public function getUri(): string
            {
                return '/x';
            }

            /**
             * @return array<string, string> 请求头
             */
            public function getHeaders(): array
            {
                return ['Host' => 'fallback.test'];
            }
        };

        $context = RequestContext::fromPsrRequest($request);

        self::assertSame('fallback.test', $context->host());
        self::assertSame('http://fallback.test/x', $context->url());
    }

    /**
     * 方法缺失的对象按缺省值处理。
     */
    public function testFromObjectWithoutMethods(): void
    {
        $context = RequestContext::fromPsrRequest(new stdClass());

        self::assertSame('GET', $context->method());
        self::assertSame('/', $context->path());
        self::assertSame('', $context->host());
        self::assertSame('', $context->body());
    }
}
