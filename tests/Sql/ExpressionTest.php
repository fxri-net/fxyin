<?php

declare(strict_types=1);

namespace Fxri\Tests\Sql;

use Fxri\Sql\Expression;
use PHPUnit\Framework\TestCase;

/**
 * 表达式工厂测试。
 */
final class ExpressionTest extends TestCase
{
    /**
     * 安全除法在除数为 0 时返回 0。
     */
    public function testDivision(): void
    {
        self::assertSame('IF(`total`=0,0,`part`/`total`)', Expression::division('`part`', '`total`')->sql());
    }

    /**
     * 距离表达式使用球面公式。
     */
    public function testDistance(): void
    {
        $sql = Expression::distance('`lng_start`', '`lat_start`', '`lng_end`', '`lat_end`')->sql();

        self::assertStringStartsWith('ACOS(', $sql);
        self::assertStringEndsWith('*6378.137', $sql);
    }

    /**
     * 首字母表达式按 GBK 区间映射。
     */
    public function testInitial(): void
    {
        $sql = Expression::initial('`name`')->sql();

        self::assertStringStartsWith('ELT(INTERVAL(', $sql);
        self::assertStringContainsString('`name` USING gbk', $sql);
        self::assertStringContainsString("UPPER(LEFT(`name`,1))", $sql);
    }
}
