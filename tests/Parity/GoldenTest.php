<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\Data\Field;
use Fxri\Data\Tree;
use Fxri\File\Path;
use Fxri\Security\Hash;
use Fxri\Support\Random;
use Fxri\Support\RandomCharset;
use Fxri\Text\Encode;
use Fxri\Text\Ip;
use Fxri\Text\Str;
use Fxri\Text\Time;
use Fxri\Text\Uuid;
use PHPUnit\Framework\TestCase;

/**
 * 纯函数 golden 对比：确定性输出逐值校验，非确定性输出校验形态。
 */
final class GoldenTest extends TestCase
{
    /**
     * 文本、摘要与编解码固定输出。
     */
    public function testTextGolden(): void
    {
        self::assertSame('5d41402abc4b2a76b9719d911017c592', Hash::md5('hello'));
        self::assertSame('414243', Encode::hexEncode('ABC'));
        self::assertSame('ABC', Encode::hexDecode('414243'));
        self::assertSame('风音-fxri', Str::implode('-', ['风音', 'fxri']));
        self::assertSame('a,b,c', Str::splice('a,b', 'c', ','));
        self::assertSame(['a', 'b'], Str::explode(',', ' a, b ,'));
    }

    /**
     * IP 与路径固定输出。
     */
    public function testNetworkGolden(): void
    {
        self::assertSame(3232235521, Ip::toLong('192.168.0.1'));
        self::assertSame('192.168.0.1', Ip::fromLong(3232235521));
        self::assertSame('/var/www/config', Path::join('/var/www', 'app', '..', 'config'));
    }

    /**
     * 时间固定输出。
     */
    public function testTimeGolden(): void
    {
        self::assertSame('2026-10-28', Time::change('2026-10-28 12:00:00', '2', 'Y-m-d'));
        self::assertSame('1小时前', Time::relative(1000, ['now' => 4600]));
        self::assertSame('2小时后', Time::relative(8200, ['now' => 1000]));
    }

    /**
     * 树与字段表达式固定输出。
     */
    public function testDataGolden(): void
    {
        $tree = Tree::fromList([
            ['id' => 1, 'parent_id' => 0],
            ['id' => 2, 'parent_id' => 1],
        ]);

        self::assertSame([
            ['id' => 1, 'parent_id' => 0, '_child' => [
                ['id' => 2, 'parent_id' => 1],
            ]],
        ], $tree);
        self::assertSame('IF(`total`=0,0,`part`/`total`)', Field::make()->division('part', 'total')->sql());
    }

    /**
     * 带种子的标识串可复现，随机串覆盖长度与字符集。
     */
    public function testNonDeterministicShape(): void
    {
        self::assertSame('93312253-460a-f8c2-cf75-6eca60055bbb', Uuid::make(seed: 'fxri'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', Uuid::make());
        self::assertSame(12, strlen(Random::string(12)));
        self::assertMatchesRegularExpression('/^[a-z]+$/', Random::string(12, RandomCharset::Lower));
    }
}
