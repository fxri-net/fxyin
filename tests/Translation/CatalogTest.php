<?php

declare(strict_types=1);

namespace Fxri\Tests\Translation;

use Fxri\File\Filesystem;
use Fxri\Translation\Catalog;
use PHPUnit\Framework\TestCase;

/**
 * 语言目录测试：嵌套词条、多文件合并与不可变写入。
 */
final class CatalogTest extends TestCase
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
     * 根目录下每个子目录是一种语言，嵌套词条原样保留。
     */
    public function testFromRootPreservesNested(): void
    {
        $root = $this->makeDirectory();
        $this->write($root, 'zh-cn', 'base.php', "<?php\nreturn ['delete' => '删除', 'lang_list' => ['zh-cn' => '中文（简体）']];\n");
        $this->write($root, 'zh-cn', 'env.php', "<?php\nreturn ['http 404' => '404 无法找到'];\n");
        $this->write($root, 'en-us', 'base.php', "<?php\nreturn ['delete' => 'delete'];\n");

        $catalog = Catalog::fromRoot($root);

        self::assertSame(['en-us', 'zh-cn'], $catalog->locales());
        self::assertSame('删除', $catalog->get('delete', 'zh-cn'));
        self::assertSame(['zh-cn' => '中文（简体）'], $catalog->get('lang_list', 'zh-cn'));
        self::assertSame('404 无法找到', $catalog->get('http 404', 'zh-cn'));
        self::assertSame('delete', $catalog->get('delete', 'en-us'));
        self::assertSame(['messages'], $catalog->domains('zh-cn'));
    }

    /**
     * 后加载的文件覆盖同名词条，并保留其它词条。
     */
    public function testLoadDirectoryOverrides(): void
    {
        $root = $this->makeDirectory();
        $this->write($root, 'zh-cn', 'base.php', "<?php\nreturn ['delete' => '删除'];\n");
        $this->write($root, 'zh-cn', 'custom.php', "<?php\nreturn ['delete' => '覆盖删除', 'extra' => '新增'];\n");

        $catalog = Catalog::fromRoot($root);

        self::assertSame('覆盖删除', $catalog->get('delete', 'zh-cn'));
        self::assertSame('新增', $catalog->get('extra', 'zh-cn'));
    }

    /**
     * 写入返回新实例，原实例保持不变。
     */
    public function testSetIsImmutable(): void
    {
        $catalog = Catalog::make();
        $written = $catalog->set('greet', '你好');

        self::assertFalse($catalog->has('greet'));
        self::assertTrue($written->has('greet'));
        self::assertSame('你好', $written->get('greet'));
        self::assertSame([], $catalog->all());
    }

    /**
     * 批量写入递归合并嵌套词条。
     */
    public function testSetMergesNested(): void
    {
        $catalog = Catalog::make()->set(['lang_list' => ['zh-cn' => '中文（简体）']]);
        $catalog = $catalog->set(['lang_list' => ['en-us' => '英语（美国）']]);

        self::assertSame(
            ['zh-cn' => '中文（简体）', 'en-us' => '英语（美国）'],
            $catalog->get('lang_list'),
        );
    }

    /**
     * 文件缺失时按空词条处理，不抛异常。
     */
    public function testLoadMissingFileIsIgnored(): void
    {
        $catalog = Catalog::make()->load(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-missing-lang.php');

        self::assertSame([], $catalog->all());
    }

    /**
     * 建立临时根目录。
     *
     * @return string 目录路径
     */
    private function makeDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-catalog-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directories[] = $directory;

        return $directory;
    }

    /**
     * 写入语言包文件。
     *
     * @param string $root 根目录
     * @param string $locale 语言
     * @param string $name 文件名
     * @param string $contents 文件内容
     * @return void
     */
    private function write(string $root, string $locale, string $name, string $contents): void
    {
        $directory = $root . DIRECTORY_SEPARATOR . $locale;
        if (!is_dir($directory)) {
            mkdir($directory);
        }
        file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $contents);
    }
}
