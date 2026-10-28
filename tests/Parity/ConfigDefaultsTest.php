<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\File\Filesystem;
use Fxri\KernelBuilder;
use PHPUnit\Framework\TestCase;

/**
 * 默认配置与覆盖顺序对等测试。
 */
final class ConfigDefaultsTest extends TestCase
{
    /** 临时目录集合 */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            Filesystem::deleteDirectory($directory);
        }
    }

    /**
     * 默认值与 1.6.1 的 app/branch/env 配置对齐。
     */
    public function testDefaults(): void
    {
        $kernel = KernelBuilder::make()->build();
        $config = $kernel->config();

        self::assertSame('PRC', $config->get('app.base.timezone'));
        self::assertSame('zh-cn', $config->get('app.lang.default'));
        self::assertSame('lang', $config->get('app.lang.name'));
        self::assertSame(['zh-cn', 'en-us'], $config->get('app.lang.list'));
        self::assertSame('master', $config->get('branch.default'));
        self::assertSame([], $config->get('branch.store.base'));
        self::assertSame('zh-cn', $config->get('env.view.lang'));
    }

    /**
     * 内置配置先加载，项目目录按登记顺序覆盖。
     */
    public function testOverrideOrder(): void
    {
        $first = $this->makeDirectory('app.php', "<?php\nreturn ['lang' => ['default' => 'en-us', 'switch' => false]];\n");
        $second = $this->makeDirectory('app.php', "<?php\nreturn ['lang' => ['default' => 'zh-cn']];\n");

        $kernel = KernelBuilder::make()->withConfigDir($first)->withConfigDir($second)->build();

        self::assertSame('zh-cn', $kernel->config()->get('app.lang.default'));
        self::assertFalse($kernel->config()->get('app.lang.switch'));
        self::assertSame('PRC', $kernel->config()->get('app.base.timezone'));
    }

    /**
     * 运行期写入只进覆盖层，只读配置不变。
     */
    public function testStore(): void
    {
        $kernel = KernelBuilder::make()->build();
        $kernel->configStore()->set('app.lang.default', 'en-us');

        self::assertSame('en-us', $kernel->configStore()->get('app.lang.default'));
        self::assertSame('zh-cn', $kernel->config()->get('app.lang.default'));
    }

    /**
     * 关闭内置配置后基底为空。
     */
    public function testWithoutDefaultConfig(): void
    {
        self::assertSame([], KernelBuilder::make()->withoutDefaultConfig()->build()->config()->all());
        self::assertSame([], KernelBuilder::make()->bare()->build()->config()->all());
    }

    /**
     * 建立临时配置目录。
     *
     * @param string $name 文件名
     * @param string $contents 内容
     * @return string 目录路径
     */
    private function makeDirectory(string $name, string $contents): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-parity-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $contents);
        $this->directories[] = $directory;

        return $directory;
    }
}
