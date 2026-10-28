<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\RequestContext;
use Fxri\Http\RequestInput;
use PHPUnit\Framework\TestCase;

/**
 * 请求入参聚合测试：覆盖多来源合并、请求体解析与按结构提取。
 */
final class RequestInputTest extends TestCase
{
    /**
     * 多来源按查询串、表单、JSON 请求体、命令行的顺序合并。
     */
    public function testAllMergesSources(): void
    {
        $input = RequestInput::make(
            ['id' => '1', 'name' => 'query'],
            ['name' => 'post'],
            '{"name":"body","page":2}',
            ['name' => 'cli', 'debug' => '1'],
        );

        self::assertSame(
            ['id' => '1', 'name' => 'cli', 'page' => 2, 'debug' => '1'],
            $input->all(),
        );
        self::assertSame('query', $input->query()['name']);
        self::assertSame('post', $input->post()['name']);
        self::assertSame(['name' => 'body', 'page' => 2], $input->json());
        self::assertSame('cli', $input->cli()['name']);
    }

    /**
     * 请求体不是 JSON 对象时不参与合并。
     */
    public function testAllIgnoresNonJsonBody(): void
    {
        $input = RequestInput::make(['id' => '1'], [], 'name=post&page=2');

        self::assertSame(['id' => '1'], $input->all());
        self::assertNull($input->json());
        self::assertSame('name=post&page=2', $input->body());
    }

    /**
     * 按合并结果取单个值，缺失时返回默认值。
     */
    public function testGet(): void
    {
        $input = RequestInput::make(['id' => '1']);

        self::assertSame('1', $input->get('id'));
        self::assertNull($input->get('missing'));
        self::assertSame('fallback', $input->get('missing', 'fallback'));
    }

    /**
     * 按结构提取时缺失键补 null，数组值递归下钻。
     */
    public function testOnly(): void
    {
        $input = RequestInput::make(['id' => '7', 'user' => ['name' => '风音']]);

        self::assertSame(
            ['id' => '7', 'missing' => null, 'user' => ['name' => '风音', 'email' => null]],
            $input->only(['id', 'missing', 'user' => ['name', 'email']]),
        );
    }

    /**
     * 从请求上下文还原查询串与请求体。
     */
    public function testFromRequest(): void
    {
        $request = RequestContext::make('POST', '/save', ['Content-Type' => 'application/json'], '{"id":9}', 'example.com', true, 'a=1&b%5B%5D=2');

        $input = RequestInput::fromRequest($request, ['form' => 'x'], ['cli' => 'y']);

        self::assertSame(['a' => '1', 'b' => ['2']], $input->query());
        self::assertSame(['form' => 'x'], $input->post());
        self::assertSame('{"id":9}', $input->body());
        self::assertSame(['cli' => 'y'], $input->cli());
        self::assertSame(['a' => '1', 'b' => ['2'], 'form' => 'x', 'id' => 9, 'cli' => 'y'], $input->all());
    }
}
