<?php

declare(strict_types=1);

namespace Fxri\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 随包分发文件编码检查：固定 LF 行尾、无 UTF-8 BOM。
 *
 * 这些文件既参与逐字节校验，也被运行时代码直接读取；平台自动转换行尾会让本地与 CI 结果不一致，
 * 因此把行尾与 BOM 放进本地同一条门禁，而不是等到 CI 才暴露。
 */
final class EncodingTest extends TestCase
{
    /** 随包分发的 PHP 目录 */
    private const DIRECTORIES = ['src', 'resources'];

    /**
     * 源文件与资源文件不得出现 CRLF 或 UTF-8 BOM。
     */
    public function testShippedFilesUseLfWithoutBom(): void
    {
        foreach (self::DIRECTORIES as $directory) {
            $files = $this->phpFiles($directory);
            self::assertNotSame([], $files, $directory . ' 目录未扫描到 PHP 文件');

            foreach ($files as $file) {
                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents, $file->getPathname());
                self::assertStringNotContainsString("\r", $contents, $file->getPathname());
                self::assertStringStartsNotWith("\xEF\xBB\xBF", $contents, $file->getPathname());
            }
        }
    }

    /**
     * 递归收集目录下的 PHP 文件。
     *
     * @param string $directory 相对仓库根目录的目录名
     * @return list<SplFileInfo> PHP 文件列表
     */
    private function phpFiles(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $directory, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file;
            }
        }

        return $files;
    }
}
