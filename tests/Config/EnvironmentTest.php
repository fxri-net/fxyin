<?php

declare(strict_types=1);

namespace Fxri\Tests\Config;

use Fxri\Config\Environment;
use Fxri\File\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * 环境变量解析测试：文本格式、文件缺失与合并顺序。
 */
final class EnvironmentTest extends TestCase
{
    /** 临时目录 */
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-env-' . bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        Filesystem::deleteDirectory($this->directory);
    }

    /**
     * 从文件解析键值对。
     */
    public function testFromFile(): void
    {
        $file = $this->directory . DIRECTORY_SEPARATOR . 'app.env';
        file_put_contents($file, "APP_NAME=fxyin\n");

        self::assertSame('fxyin', Environment::fromFile($file)->get('APP_NAME'));
    }

    /**
     * 文件不存在时返回空集合。
     */
    public function testFromFileMissingReturnsEmpty(): void
    {
        $environment = Environment::fromFile($this->directory . DIRECTORY_SEPARATOR . 'missing.env');

        self::assertSame([], $environment->all());
        self::assertSame('fallback', $environment->get('APP_NAME', 'fallback'));
    }

    /**
     * 跳过空行与整行注释，支持 export 前缀与成对引号。
     */
    public function testFromString(): void
    {
        $environment = Environment::fromString(
            "# 注释行\n\nAPP_NAME = \"风音框架\"\nexport APP_DEBUG='true'\nEMPTY=\nINVALID\n",
        );

        self::assertSame('风音框架', $environment->get('APP_NAME'));
        self::assertSame('true', $environment->get('APP_DEBUG'));
        self::assertSame('', $environment->get('EMPTY'));
        self::assertFalse($environment->has('INVALID'));
    }

    /**
     * 合并时同名键以后者为准，原实例不变。
     */
    public function testMergeOverrides(): void
    {
        $base = Environment::fromString("APP_NAME=base\nAPP_DEBUG=true\n");
        $merged = $base->merge(Environment::fromString("APP_NAME=overlay\n"));

        self::assertSame('overlay', $merged->get('APP_NAME'));
        self::assertSame('true', $merged->get('APP_DEBUG'));
        self::assertSame('base', $base->get('APP_NAME'));
    }
}
