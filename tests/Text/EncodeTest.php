<?php

declare(strict_types=1);

namespace Fxri\Tests\Text;

use Fxri\Text\Encode;
use Fxri\Text\EncodeConvert;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 编解码测试：类型转换、嵌套查询串与首字母。
 */
final class EncodeTest extends TestCase
{
    /**
     * 字符串与十六进制互换。
     */
    public function testConvert(): void
    {
        self::assertSame('4142', Encode::convert('AB', EncodeConvert::ToHex));
        self::assertSame('AB', Encode::convert('4142', EncodeConvert::FromHex));
    }

    /**
     * 单数字符串按十六进制解码拒绝。
     */
    public function testConvertRejectsOddHex(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Encode::convert('abc', EncodeConvert::FromHex);
    }

    /**
     * 嵌套数组编码后可还原。
     */
    public function testNestedQuery(): void
    {
        $data = ['a' => ['b' => '1'], 'c' => '中文'];
        $query = Encode::encodeAll($data);

        self::assertSame('a%5Bb%5D=1&c=%E4%B8%AD%E6%96%87', $query);
        self::assertSame($data, Encode::decodeAll($query));
    }

    /**
     * 英文字母转大写，中文按 GBK 区间取首字母。
     */
    public function testLetters(): void
    {
        self::assertSame('A', Encode::letter('a'));
        self::assertSame('Z', Encode::letter('中'));
        self::assertSame('ZW', Encode::letters('中文'));
        self::assertSame('AB', Encode::letters('ab'));
    }
}
