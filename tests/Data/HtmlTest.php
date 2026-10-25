<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Html;
use PHPUnit\Framework\TestCase;

/**
 * HTML 处理测试：转义、去标签与数组递归。
 */
final class HtmlTest extends TestCase
{
    /**
     * 转义尖括号。
     */
    public function testEscapeString(): void
    {
        self::assertSame('&lt;b&gt;风音&lt;/b&gt;', Html::escape('<b>风音</b>'));
    }

    /**
     * 已有实体不重复编码。
     */
    public function testEscapeKeepsEntity(): void
    {
        self::assertSame('&amp;', Html::escape('&amp;'));
    }

    /**
     * 数组按元素递归转义。
     */
    public function testEscapeArray(): void
    {
        self::assertSame(['a' => '&lt;x&gt;', 'b' => 1], Html::escape(['a' => '<x>', 'b' => 1]));
    }

    /**
     * 非字符串原样返回。
     */
    public function testEscapeOtherType(): void
    {
        self::assertSame(1, Html::escape(1));
    }

    /**
     * 去掉全部标签或只保留指定标签。
     */
    public function testStrip(): void
    {
        self::assertSame('风音', Html::strip('<b>风音</b>'));
        self::assertSame('<b>风音</b>x', Html::strip('<b>风音</b><i>x</i>', '<b>'));
    }

    /**
     * 数组按元素递归去标签。
     */
    public function testStripArray(): void
    {
        self::assertSame(['x'], Html::strip(['<i>x</i>']));
    }
}
