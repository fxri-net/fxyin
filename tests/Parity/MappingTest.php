<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\Data\Cast;
use Fxri\Data\Hierarchy;
use Fxri\Http\Envelope;
use Fxri\Http\JsonFormatter;
use Fxri\Http\XmlFormatter;
use Fxri\Param\Picker;
use Fxri\Support\Random;
use Fxri\Support\RandomCharset;
use Fxri\Text\Encode;
use Fxri\Text\Str;
use PHPUnit\Framework\TestCase;

/**
 * 对等映射测试：逐项证明修正后的矩阵目标可承接 1.x 语义。
 */
final class MappingTest extends TestCase
{
    /**
     * `Text::strEncode` / `strDecode` 由嵌套查询串编解码承接。
     */
    public function testNestedEncodeMapping(): void
    {
        $data = ['a' => 1, 'b' => ['c' => 2]];

        self::assertSame('a=1&b%5Bc%5D=2', Encode::encodeAll($data));
        self::assertSame(['a' => '1', 'b' => ['c' => '2']], Encode::decodeAll(Encode::encodeAll($data)));
    }

    /**
     * `Text::strlen` 由长度区间校验承接。
     */
    public function testLengthMapping(): void
    {
        self::assertTrue(Str::isLength('风音', 2, 2));
        self::assertFalse(Str::isLength('风音', 3));
        self::assertTrue(Str::isLength('风音', null, 2));
    }

    /**
     * `Math::rand` 的混合类型首字符保证由 `Alpha` 承接。
     */
    public function testRandomFirstCharset(): void
    {
        for ($try = 0; $try < 20; ++$try) {
            self::assertMatchesRegularExpression('/^[A-Za-z][0-9A-Za-z]{9}$/', Random::string(10, RandomCharset::Alnum, RandomCharset::Alpha));
        }

        self::assertSame(52, RandomCharset::Alpha->length());
    }

    /**
     * `View::echo` 由格式化器按类型承接。
     */
    public function testViewFormatterMapping(): void
    {
        $envelope = Envelope::success(['id' => 7], '获取成功');

        self::assertSame('{"ok":true,"code":"ok","message":"获取成功","data":{"id":7}}', JsonFormatter::make()->format($envelope));
        self::assertStringContainsString('<response>', XmlFormatter::make()->format($envelope));
        self::assertStringContainsString('<id>7</id>', XmlFormatter::make()->format($envelope));
    }

    /**
     * `Server::system` 由 PHP 原生系统族承接。
     */
    public function testPlatformMapping(): void
    {
        self::assertNotSame('', PHP_OS_FAMILY);
    }

    /**
     * `Data::storeUpperLevel` / `storeLowerLevel` 由应用层递归承接。
     */
    public function testHierarchyMapping(): void
    {
        $list = [
            ['id' => 1, 'parent_id' => 0],
            ['id' => 2, 'parent_id' => 1],
            ['id' => 3, 'parent_id' => 2],
        ];

        self::assertSame([2, 1], Hierarchy::ancestors($list, 3));
        self::assertSame([2, 3], Hierarchy::descendants($list, 1));
    }

    /**
     * `Param::array` / `object` / `pick` 由 `Cast` 与 `Picker` 承接。
     */
    public function testParamMapping(): void
    {
        self::assertSame(['a' => 1, 'b' => ['c' => 2]], Cast::toArray(['a' => 1, 'b' => ['c' => 2]]));
        self::assertEquals((object) ['a' => 1], Cast::toObject(['a' => 1]));
        self::assertSame(['a' => 1, 'missing' => null], Picker::pick(['a' => 1], ['a', 'missing']));
    }
}
