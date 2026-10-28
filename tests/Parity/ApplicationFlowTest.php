<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\Application\Bootstrap;
use Fxri\Application\Router;
use Fxri\Application\Services;
use Fxri\File\Filesystem;
use Fxri\Http\RequestContext;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 应用启动、服务与路由对等测试。
 */
final class ApplicationFlowTest extends TestCase
{
    /** 临时目录集合 */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            Filesystem::deleteDirectory($directory);
        }
        unset($GLOBALS['fxri_parity_function'], $GLOBALS['fxri_parity_store']);
    }

    /**
     * 启动按 config → language → function → store 顺序装配，并执行三阶段钩子。
     */
    public function testBootFlow(): void
    {
        $root = $this->makeProject();
        $order = [];

        $bootstrap = Bootstrap::make($root)
            ->withHook(Bootstrap::PHASE_LOAD, static function () use (&$order): void {
                $order[] = 'load';
            })
            ->withHook(Bootstrap::PHASE_START, static function () use (&$order): void {
                $order[] = 'start';
            })
            ->withHook(Bootstrap::PHASE_READY, static function () use (&$order): void {
                $order[] = 'ready';
            });

        $kernel = $bootstrap->boot();

        self::assertSame(['load', 'start', 'ready'], $order);
        self::assertSame('en-us', $kernel->config()->get('app.lang.default'));
        self::assertSame('custom delete', $kernel->language()?->get('delete'));
        self::assertTrue($GLOBALS['fxri_parity_function'] ?? false);
        self::assertSame('en-us', $GLOBALS['fxri_parity_store'] ?? null);
    }

    /**
     * 服务工厂首次解析后缓存。
     */
    public function testServices(): void
    {
        $calls = 0;
        $bootstrap = Bootstrap::make()->withFactory('lazy', static function (Services $services) use (&$calls): object {
            ++$calls;

            return new stdClass();
        });

        $first = $bootstrap->services()->get('lazy');
        $second = $bootstrap->services()->get('lazy');

        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    /**
     * 路由解析与解析器委托。
     */
    public function testRouter(): void
    {
        $router = Router::make('Demo', 'Controller', static fn (string $controller, string $method): string => $controller . '::' . $method);
        $request = RequestContext::make('GET', '/admin/user/info');

        self::assertSame(['\\Demo\\admin\\UserController', 'info'], $router->parse($request));
        self::assertSame('\\Demo\\admin\\UserController::info', $router->dispatch($request));
    }

    /**
     * 建立项目目录。
     *
     * @return string 项目路径
     */
    private function makeProject(): string
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-parity-app-' . bin2hex(random_bytes(6));
        mkdir($root);
        mkdir($root . '/config');
        mkdir($root . '/language/en-us', 0o777, true);
        mkdir($root . '/function');
        mkdir($root . '/store');
        $this->directories[] = $root;

        file_put_contents($root . '/config/app.php', "<?php\nreturn ['lang' => ['default' => 'en-us']];\n");
        file_put_contents($root . '/language/en-us/custom.php', "<?php\nreturn ['delete' => 'custom delete'];\n");
        file_put_contents($root . '/function/flag.php', "<?php\n\$GLOBALS['fxri_parity_function'] = true;\n");
        file_put_contents(
            $root . '/store/ready.php',
            "<?php\nreturn static function (\\Fxri\\Application\\Bootstrap \$bootstrap, \\Fxri\\Kernel \$kernel, \\Fxri\\Application\\Services \$services): void {\n"
            . "    \$GLOBALS['fxri_parity_store'] = \$kernel->config()->get('app.lang.default');\n};\n",
        );

        return $root;
    }
}
