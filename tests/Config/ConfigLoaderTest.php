<?php

declare(strict_types=1);

namespace Fxri\Tests\Config;

use Fxri\Config\ConfigLoader;
use Fxri\File\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * 配置加载器测试：递归子目录、点号文件名、加载顺序与结构校验。
 */
final class ConfigLoaderTest extends TestCase
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
     * 平铺目录的键位与原来一致。
     */
    public function testFlatDirectory(): void
    {
        $directory = $this->makeDirectory();
        $this->write($directory, 'app.php', "<?php\nreturn ['name' => 'fxyin'];\n");

        self::assertSame(['name' => 'fxyin'], ConfigLoader::make($directory)->load()->get('app'));
    }

    /**
     * 子目录相对路径转为嵌套键，多级目录逐层展开。
     */
    public function testNestedDirectory(): void
    {
        $directory = $this->makeDirectory();
        $this->write($directory, 'facade/model.php', "<?php\nreturn ['name' => 'model'];\n");
        $this->write($directory, 'facade/http/request.php', "<?php\nreturn ['name' => 'request'];\n");

        $config = ConfigLoader::make($directory)->load();

        self::assertSame('model', $config->get('facade.model.name'));
        self::assertSame('request', $config->get('facade.http.request.name'));
    }

    /**
     * 点号文件名与同名子目录等价。
     */
    public function testDotFilename(): void
    {
        $dot = $this->makeDirectory();
        $this->write($dot, 'third.oss.php', "<?php\nreturn ['driver' => 'local'];\n");

        $nested = $this->makeDirectory();
        $this->write($nested, 'third/oss.php', "<?php\nreturn ['driver' => 'local'];\n");

        self::assertSame(
            ConfigLoader::make($nested)->load()->all(),
            ConfigLoader::make($dot)->load()->all(),
        );
        self::assertSame('local', ConfigLoader::make($dot)->load()->get('third.oss.driver'));
    }

    /**
     * 同一层先文件后子目录，后加载覆盖先加载。
     */
    public function testLoadOrder(): void
    {
        $directory = $this->makeDirectory();
        $this->write($directory, 'third.oss.php', "<?php\nreturn ['driver' => 'flat'];\n");
        $this->write($directory, 'third/oss.php', "<?php\nreturn ['driver' => 'nested'];\n");

        self::assertSame('nested', ConfigLoader::make($directory)->load()->get('third.oss.driver'));
    }

    /**
     * 同名分组递归合并，子目录只覆盖给出的键。
     */
    public function testDeepMerge(): void
    {
        $directory = $this->makeDirectory();
        $this->write($directory, 'app.php', "<?php\nreturn ['lang' => ['default' => 'zh-cn', 'list' => ['zh-cn', 'en-us']]];\n");
        $this->write($directory, 'app/lang.php', "<?php\nreturn ['default' => 'en-us'];\n");

        $config = ConfigLoader::make($directory)->load();

        self::assertSame('en-us', $config->get('app.lang.default'));
        self::assertSame(['zh-cn', 'en-us'], $config->get('app.lang.list'));
    }

    /**
     * 配置文件返回值不是数组时抛出异常。
     */
    public function testNonArrayThrows(): void
    {
        $directory = $this->makeDirectory();
        $this->write($directory, 'broken.php', "<?php\nreturn 'oops';\n");

        $this->expectException(RuntimeException::class);

        ConfigLoader::make($directory)->load();
    }

    /**
     * 目录不存在时按空配置处理。
     */
    public function testMissingDirectory(): void
    {
        $config = ConfigLoader::make(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-config-missing-' . bin2hex(random_bytes(6)))->load();

        self::assertSame([], $config->all());
    }

    /**
     * 建立临时配置目录。
     *
     * @return string 目录路径
     */
    private function makeDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-config-loader-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * 写入配置文件，自动建立所需子目录。
     *
     * @param string $directory 配置目录
     * @param string $relative 相对文件路径
     * @param string $contents 文件内容
     * @return void
     */
    private function write(string $directory, string $relative, string $contents): void
    {
        $path = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $parent = dirname($path);
        if (!is_dir($parent)) {
            mkdir($parent, 0o777, true);
        }
        file_put_contents($path, $contents);
    }
}
