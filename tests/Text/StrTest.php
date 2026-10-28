<?php

declare(strict_types=1);

namespace Fxri\Tests\Text;

use Fxri\Text\Str;
use PHPUnit\Framework\TestCase;

/**
 * 字符串拼接测试。
 */
final class StrTest extends TestCase
{
    /**
     * 空值直接返回另一侧，重复片段不重复追加。
     */
    public function testSplice(): void
    {
        self::assertSame('c', Str::splice('', 'c'));
        self::assertSame('a,b,c', Str::splice('a,b', 'c', ','));
        self::assertSame('a,b', Str::splice('a,b', 'b', ','));
        self::assertSame('a,b', Str::splice('a,b', ''));
    }
}
