<?php

declare(strict_types=1);

namespace Fxri\Tests\Http;

use Fxri\Http\BranchContext;
use PHPUnit\Framework\TestCase;

/**
 * 分支上下文测试：完整主机、逗号键、前缀与正则匹配。
 */
final class BranchContextTest extends TestCase
{
    /**
     * 完整主机名优先，逗号键内任一主机都命中同一条配置。
     */
    public function testFullMatch(): void
    {
        $context = BranchContext::resolve('b.com', $this->store(), 'default');

        self::assertSame('a.com,b.com', $context->branch());
        self::assertSame('AB', $context->config()['name']);
        self::assertSame('b.com', $context->host());
    }

    /**
     * `^` 前缀按部分正则匹配。
     */
    public function testPrefixMatch(): void
    {
        $context = BranchContext::resolve('test.example.com', $this->store(), 'default');

        self::assertSame('^test', $context->branch());
        self::assertSame('前缀', $context->config()['name']);
    }

    /**
     * `$` 前缀按完整正则匹配。
     */
    public function testRegexMatch(): void
    {
        $context = BranchContext::resolve('api.example.com', $this->store(), 'default');

        self::assertSame('$^api\..*$', $context->branch());
        self::assertSame('正则', $context->config()['name']);
    }

    /**
     * 未命中时使用默认分支。
     */
    public function testDefault(): void
    {
        $context = BranchContext::resolve('unknown.com', $this->store(), 'default');

        self::assertSame('default', $context->branch());
        self::assertSame('默认', $context->config()['name']);
    }

    /**
     * 分支配置。
     *
     * @return array<string, array{name: string}> 配置
     */
    private function store(): array
    {
        return [
            'default' => ['name' => '默认'],
            '$^api\..*$' => ['name' => '正则'],
            '^test' => ['name' => '前缀'],
            'a.com,b.com' => ['name' => 'AB'],
        ];
    }
}
