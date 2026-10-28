<?php

declare(strict_types=1);

namespace Fxri\Tests\File;

use Fxri\File\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * 文件系统测试：覆盖递归列文件、扩展名过滤与深度限制。
 */
final class FilesystemTest extends TestCase
{
    /** 临时根目录 */
    private string $root;

    /**
     * 建立临时目录树。
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-filesystem-' . bin2hex(random_bytes(6));
        Filesystem::makeDirectory($this->path('sub', 'deep'));
        Filesystem::write($this->path('a.txt'), 'a');
        Filesystem::write($this->path('b.PHP'), 'b');
        Filesystem::write($this->path('sub', 'c.txt'), 'c');
        Filesystem::write($this->path('sub', 'deep', 'd.md'), 'd');
    }

    /**
     * 清理临时目录。
     */
    protected function tearDown(): void
    {
        $link = $this->path('link');
        if (is_link($link)) {
            @unlink($link);
        }

        Filesystem::deleteDirectory($this->root);
    }

    /**
     * 不限深度时递归返回全部文件。
     */
    public function testFindFilesRecursive(): void
    {
        self::assertSame(
            [
                $this->path('a.txt'),
                $this->path('b.PHP'),
                $this->path('sub', 'c.txt'),
                $this->path('sub', 'deep', 'd.md'),
            ],
            Filesystem::findFiles($this->root),
        );
    }

    /**
     * 扩展名过滤不区分大小写，允许带点号。
     */
    public function testFindFilesByExtension(): void
    {
        self::assertSame(
            [$this->path('a.txt'), $this->path('sub', 'c.txt')],
            Filesystem::findFiles($this->root, ['txt']),
        );
        self::assertSame([$this->path('b.PHP')], Filesystem::findFiles($this->root, ['.PHP']));
        self::assertSame([], Filesystem::findFiles($this->root, ['ini']));
    }

    /**
     * 深度为 0 只取当前层，为 1 时再下探一层。
     */
    public function testFindFilesDepth(): void
    {
        self::assertSame(
            [$this->path('a.txt'), $this->path('b.PHP')],
            Filesystem::findFiles($this->root, [], 0),
        );
        self::assertSame(
            [$this->path('a.txt'), $this->path('b.PHP'), $this->path('sub', 'c.txt')],
            Filesystem::findFiles($this->root, [], 1),
        );
    }

    /**
     * 目录不存在时返回空列表。
     */
    public function testFindFilesMissingDirectory(): void
    {
        self::assertSame([], Filesystem::findFiles($this->path('missing')));
    }

    /**
     * 符号链接目录不跟随，避免环形目录。
     */
    public function testFindFilesSkipsSymlinkDirectory(): void
    {
        $link = $this->path('link');
        if (!@symlink($this->path('sub'), $link)) {
            self::markTestSkipped('当前环境不支持创建符号链接');
        }

        self::assertSame(
            [
                $this->path('a.txt'),
                $this->path('b.PHP'),
                $this->path('sub', 'c.txt'),
                $this->path('sub', 'deep', 'd.md'),
            ],
            Filesystem::findFiles($this->root),
        );
    }

    /**
     * 单层列举保持原有行为，包含子目录本身。
     */
    public function testListFilesKeepsSingleLayer(): void
    {
        $items = Filesystem::listFiles($this->root);
        sort($items);

        // glob 在 Windows 上返回正斜杠，比较前统一分隔符
        self::assertSame(
            [
                str_replace('\\', '/', $this->path('a.txt')),
                str_replace('\\', '/', $this->path('b.PHP')),
                str_replace('\\', '/', $this->path('sub')),
            ],
            array_map(static fn (string $item): string => str_replace('\\', '/', $item), $items),
        );
    }

    /**
     * 拼接临时目录下的路径。
     *
     * @param string ...$segments 路径段
     * @return string 路径
     */
    private function path(string ...$segments): string
    {
        return $this->root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }
}
