<?php

declare(strict_types=1);

namespace Fxri\Tests\Application;

use Fxri\Application\Bootstrap;
use Fxri\Application\Module;
use Fxri\Application\Services;
use Fxri\File\Filesystem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * 应用启动测试：目录扫描、钩子顺序、服务与模块注册。
 */
final class BootstrapTest extends TestCase
{
    /** 临时目录集合 */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            Filesystem::deleteDirectory($directory);
        }
        unset($GLOBALS['fxri_bootstrap_function'], $GLOBALS['fxri_bootstrap_store'], $GLOBALS['fxri_module_hook']);
    }

    /**
     * 启动前不加载函数，启动时按项目目录装配配置、语言、函数与启动脚本。
     */
    public function testBootScansProject(): void
    {
        $root = $this->makeProject();

        $bootstrap = Bootstrap::make($root);
        self::assertArrayNotHasKey('fxri_bootstrap_function', $GLOBALS);
        self::assertNull($bootstrap->kernel());

        $kernel = $bootstrap->boot();

        self::assertSame($kernel, $bootstrap->kernel());
        self::assertSame('en-us', $kernel->config()->get('app.lang.default'));
        self::assertSame('en-us', $kernel->language()?->locale());
        self::assertSame('custom delete', $kernel->language()?->get('delete'));
        self::assertTrue($GLOBALS['fxri_bootstrap_function'] ?? false);
        self::assertSame('en-us', $GLOBALS['fxri_bootstrap_store'] ?? null);
        self::assertSame($kernel, $bootstrap->boot());
    }

    /**
     * 钩子按加载、启动、就绪顺序执行，加载阶段尚无内核。
     */
    public function testHookOrder(): void
    {
        $order = [];
        $kernelAtLoad = null;
        $kernelAtStart = null;

        $bootstrap = Bootstrap::make()
            ->withHook(Bootstrap::PHASE_LOAD, static function (Bootstrap $app) use (&$order, &$kernelAtLoad): void {
                $order[] = Bootstrap::PHASE_LOAD;
                $kernelAtLoad = $app->kernel();
            })
            ->withHook(Bootstrap::PHASE_START, static function (Bootstrap $app) use (&$order, &$kernelAtStart): void {
                $order[] = Bootstrap::PHASE_START;
                $kernelAtStart = $app->kernel();
            })
            ->withHook(Bootstrap::PHASE_READY, static function () use (&$order): void {
                $order[] = Bootstrap::PHASE_READY;
            });

        $kernel = $bootstrap->boot();

        self::assertSame([Bootstrap::PHASE_LOAD, Bootstrap::PHASE_START, Bootstrap::PHASE_READY], $order);
        self::assertNull($kernelAtLoad);
        self::assertSame($kernel, $kernelAtStart);
    }

    /**
     * 服务实例与工厂都在启动后可解析。
     */
    public function testServices(): void
    {
        $service = new stdClass();
        $bootstrap = Bootstrap::make()
            ->withService('demo', $service)
            ->withFactory('lazy', static fn (Services $services): object => new stdClass());

        $bootstrap->boot();

        self::assertSame($service, $bootstrap->services()->get('demo'));
        self::assertInstanceOf(stdClass::class, $bootstrap->services()->get('lazy'));
    }

    /**
     * 模块可追加配置、服务与钩子。
     */
    public function testModule(): void
    {
        $root = $this->makeDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'config');
        file_put_contents($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'demo.php', "<?php\nreturn ['name' => '演示模块'];\n");

        $module = new class ($root) implements Module {
            /**
             * 初始化。
             *
             * @param string $root 配置目录
             */
            public function __construct(private readonly string $root)
            {
            }

            /**
             * 注册模块。
             *
             * @param Bootstrap $bootstrap 启动器
             * @return Bootstrap 启动器
             */
            public function register(Bootstrap $bootstrap): Bootstrap
            {
                return $bootstrap
                    ->withConfigDir($this->root . DIRECTORY_SEPARATOR . 'config')
                    ->withService('module.service', new stdClass())
                    ->withHook(Bootstrap::PHASE_READY, static function (): void {
                        $GLOBALS['fxri_module_hook'] = true;
                    });
            }
        };

        $bootstrap = Bootstrap::make()->withModule($module);
        $kernel = $bootstrap->boot();

        self::assertSame('演示模块', $kernel->config()->get('demo.name'));
        self::assertInstanceOf(stdClass::class, $bootstrap->services()->get('module.service'));
        self::assertTrue($GLOBALS['fxri_module_hook'] ?? false);
    }

    /**
     * 项目目录不存在时立即失败。
     */
    public function testMissingPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('应用目录不存在');

        Bootstrap::make(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-missing-app-' . bin2hex(random_bytes(6)));
    }

    /**
     * 未知生命周期阶段被拒绝。
     */
    public function testInvalidPhase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('未知的生命周期阶段');

        Bootstrap::make()->withHook('unknown', static function (): void {
        });
    }

    /**
     * 函数文件不存在时立即失败。
     */
    public function testMissingFunctionFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('函数文件不存在');

        Bootstrap::make()->withFunctions(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-missing-function.php');
    }

    /**
     * 不开命令行解析时不读取进程参数。
     */
    public function testCliDisabledByDefault(): void
    {
        $kernel = Bootstrap::make()->boot();

        self::assertNull($kernel->configStore()->get('app.param.cli'));
    }

    /**
     * 命令行参数写入配置，并给运行环境兜底主机与路径。
     */
    public function testCliArguments(): void
    {
        $bootstrap = Bootstrap::make()->withCliArguments([
            'bin.php',
            '@svr_name=Example.COM',
            '@svr_uri=/Api',
            '@app.debug.switch=1',
            'run',
        ]);

        $store = $bootstrap->boot()->configStore();

        self::assertSame('Example.COM', $store->get('app.param.cli.svr_name'));
        self::assertSame('1', $store->get('app.param.cli.app.debug.switch'));
        self::assertFalse($store->get('app.debug.switch'));
        self::assertSame('example.com', $store->get('env.base.host'));
        self::assertSame('/api', $store->get('env.base.uri'));
    }

    /**
     * 建立含配置、语言、函数与启动脚本的项目目录。
     *
     * @return string 项目目录
     */
    private function makeProject(): string
    {
        $root = $this->makeDirectory();
        mkdir($root . DIRECTORY_SEPARATOR . 'config');
        mkdir($root . DIRECTORY_SEPARATOR . 'language' . DIRECTORY_SEPARATOR . 'en-us', 0o777, true);
        mkdir($root . DIRECTORY_SEPARATOR . 'function');
        mkdir($root . DIRECTORY_SEPARATOR . 'store');

        file_put_contents($root . '/config/app.php', "<?php\nreturn ['lang' => ['default' => 'en-us']];\n");
        file_put_contents($root . '/language/en-us/custom.php', "<?php\nreturn ['delete' => 'custom delete'];\n");
        file_put_contents($root . '/function/flag.php', "<?php\n\$GLOBALS['fxri_bootstrap_function'] = true;\n");
        file_put_contents(
            $root . '/store/ready.php',
            "<?php\nreturn static function (\\Fxri\\Application\\Bootstrap \$bootstrap, \\Fxri\\Kernel \$kernel, \\Fxri\\Application\\Services \$services): void {\n"
            . "    \$GLOBALS['fxri_bootstrap_store'] = \$kernel->config()->get('app.lang.default');\n};\n",
        );

        return $root;
    }

    /**
     * 建立临时目录。
     *
     * @return string 目录路径
     */
    private function makeDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-app-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }
}
