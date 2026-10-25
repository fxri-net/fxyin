<?php

declare(strict_types=1);

namespace Fxri\Tests\File;

use Fxri\File\Path;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 路径处理测试：覆盖拼接前缀保留、分隔符归一与 .. 消解。
 */
final class PathTest extends TestCase
{
    /**
     * 拼接保留首段的绝对前缀，并归一化各段。
     *
     * @param list<string> $segments 路径片段
     * @param string $expected 期望结果
     */
    #[DataProvider('joinProvider')]
    public function testJoin(array $segments, string $expected): void
    {
        self::assertSame($expected, Path::join(...$segments));
    }

    /**
     * 拼接用例。
     *
     * @return array<string, array{list<string>, string}> 用例集合
     */
    public static function joinProvider(): array
    {
        return [
            '绝对路径保留前导斜杠' => [['/tmp', 'a'], '/tmp/a'],
            '段间多余分隔符归一' => [['/tmp/', '/a/'], '/tmp/a'],
            '反斜杠转为正斜杠' => [['C:\\www\\', 'app'], 'C:/www/app'],
            '协议头不被破坏' => [['https://example.com/', 'a/b'], 'https://example.com/a/b'],
            '根路径单独拼接' => [['/'], '/'],
            '首段为空时取其后的段' => [['', 'a'], 'a'],
            '消解上级目录' => [['/var/www', 'app', '..', 'config'], '/var/www/config'],
            '越界上级目录被丢弃' => [['a', '..', '..', 'b'], 'b'],
            '无参数返回空串' => [[], ''],
        ];
    }

    /**
     * 规范化保留绝对路径的前导斜杠。
     */
    public function testNormalizeKeepsAbsolute(): void
    {
        self::assertSame('/var/www/index.php', Path::normalize('/var/www/./app/../index.php'));
    }

    /**
     * 规范化保留盘符前缀。
     */
    public function testNormalizeKeepsDrive(): void
    {
        self::assertSame('C:/www/index.php', Path::normalize('C:\\www\\.\\index.php'));
    }

    /**
     * 规范化保留协议头。
     */
    public function testNormalizeKeepsScheme(): void
    {
        self::assertSame('https://example.com/a', Path::normalize('https://example.com/./a'));
    }

    /**
     * 相对路径规范化后仍为相对路径。
     */
    public function testNormalizeKeepsRelative(): void
    {
        self::assertSame('a/b', Path::normalize('a/./b'));
    }
}
