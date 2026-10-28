<?php

declare(strict_types=1);

namespace Fxri\Tests\File;

use Fxri\File\Filesystem;
use Fxri\File\Loader;
use PHPUnit\Framework\TestCase;

/**
 * 文件加载测试：只执行一次、数据读取与目录创建。
 */
final class LoaderTest extends TestCase
{
    /** 临时目录集合 */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            Filesystem::deleteDirectory($directory);
        }
        unset($GLOBALS['fxri_loader_count']);
    }

    /**
     * 副作用脚本同一路径只执行一次，缺失文件返回 false。
     */
    public function testLoadOnce(): void
    {
        $file = $this->makeFile('side.php', "<?php\n\$GLOBALS['fxri_loader_count'] = (\$GLOBALS['fxri_loader_count'] ?? 0) + 1;\n");

        self::assertTrue(Loader::load($file));
        self::assertTrue(Loader::load($file));
        self::assertSame(1, $GLOBALS['fxri_loader_count'] ?? 0);
        self::assertFalse(Loader::load($file . '.missing'));
    }

    /**
     * 数据文件返回原始结构并缓存。
     */
    public function testRead(): void
    {
        $file = $this->makeFile('data.php', "<?php\nreturn ['name' => '风音', 'nested' => ['a' => 1]];\n");

        self::assertSame(['name' => '风音', 'nested' => ['a' => 1]], Loader::read($file));
        self::assertSame(['name' => '风音', 'nested' => ['a' => 1]], Loader::read($file));
        self::assertFalse(Loader::read($file . '.missing'));
    }

    /**
     * 目录创建支持多级路径。
     */
    public function testCreateDirectory(): void
    {
        $root = $this->makeDirectory();
        $target = $root . DIRECTORY_SEPARATOR . 'a' . DIRECTORY_SEPARATOR . 'b';

        Filesystem::createDirectory($target);

        self::assertDirectoryExists($target);
    }

    /**
     * 建立临时目录。
     *
     * @return string 目录路径
     */
    private function makeDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-loader-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * 在临时目录写入文件。
     *
     * @param string $name 文件名
     * @param string $contents 内容
     * @return string 文件路径
     */
    private function makeFile(string $name, string $contents): string
    {
        $file = $this->makeDirectory() . DIRECTORY_SEPARATOR . $name;
        file_put_contents($file, $contents);

        return $file;
    }
}
