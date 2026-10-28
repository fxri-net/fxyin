<?php

declare(strict_types=1);

namespace Fxri\Tests\Application;

use Fxri\Application\Router;
use Fxri\Exception\RouteNotFoundException;
use Fxri\Http\RequestContext;
use PHPUnit\Framework\TestCase;

/**
 * 可选路由测试：路径解析、解析器委托与异常行为。
 */
final class RouterTest extends TestCase
{
    /**
     * 路径最后一段为方法，其余段为控制器路径。
     */
    public function testParse(): void
    {
        $router = Router::make('Demo', 'Controller');
        $request = RequestContext::make('GET', '/admin/user/info');

        self::assertSame('\\Demo\\admin\\UserController', $router->controller($request));
        self::assertSame('info', $router->method($request));
        self::assertSame(['\\Demo\\admin\\UserController', 'info'], $router->parse($request));
    }

    /**
     * 项目解析器接管控制器执行。
     */
    public function testResolver(): void
    {
        $router = Router::make('Demo', '', static function (string $controller, string $method, RequestContext $request): string {
            return implode('|', [$controller, $method, $request->path()]);
        });

        self::assertSame('\\Demo\\User|info|/user/info', $router->dispatch(RequestContext::make('GET', '/user/info')));
    }

    /**
     * 默认解析器直接实例化控制器并调用方法。
     */
    public function testDispatch(): void
    {
        self::assertSame('ok', Router::make('Fxri\\Tests\\Application')->dispatch(RequestContext::make('GET', '/fixture/ok')));
    }

    /**
     * 控制器不存在时抛可映射异常。
     */
    public function testMissingController(): void
    {
        try {
            Router::make('Fxri\\Tests\\Application')->dispatch(RequestContext::make('GET', '/missing/ok'));
            self::fail('未抛出路由异常');
        } catch (RouteNotFoundException $exception) {
            self::assertSame('\\Fxri\\Tests\\Application\\Missing', $exception->controller());
            self::assertSame('ok', $exception->method());
            self::assertStringContainsString('控制器不存在', $exception->getMessage());
        }
    }

    /**
     * 方法不存在时抛可映射异常。
     */
    public function testMissingMethod(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('方法不存在');

        Router::make('Fxri\\Tests\\Application')->dispatch(RequestContext::make('GET', '/fixture/missing'));
    }

    /**
     * 空路径与单段路径都无法解析。
     */
    public function testInvalidPath(): void
    {
        $router = Router::make();

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(RequestContext::make('GET', '/'));
    }

    /**
     * 单段路径同样拒绝。
     */
    public function testSingleSegment(): void
    {
        $this->expectException(RouteNotFoundException::class);

        Router::make()->parse(RequestContext::make('GET', '/only'));
    }

    /**
     * 命名空间与后缀可替换，原实例不变。
     */
    public function testWithOptions(): void
    {
        $router = Router::make()->withNamespace('Demo')->withSuffix('Controller');
        $request = RequestContext::make('GET', '/user/info');

        self::assertSame('\\Demo\\UserController', $router->controller($request));
        self::assertSame('\\App\\User', Router::make()->controller($request));
    }
}

/**
 * 路由测试用控制器。
 */
final class Fixture
{
    /**
     * 返回固定值。
     *
     * @return string 固定值
     */
    public function ok(): string
    {
        return 'ok';
    }
}
