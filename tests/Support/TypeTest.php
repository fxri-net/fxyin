<?php

declare(strict_types=1);

namespace Fxri\Tests\Support;

use Fxri\Support\Type;
use PHPUnit\Framework\TestCase;
use Stringable;

/**
 * 类型判定与标量转换测试。
 */
final class TypeTest extends TestCase
{
    /**
     * 空值判定只认 null 与空串。
     */
    public function testIsBlank(): void
    {
        self::assertTrue(Type::isBlank(null));
        self::assertTrue(Type::isBlank(''));
        self::assertFalse(Type::isBlank(0));
        self::assertFalse(Type::isBlank(false));
        self::assertFalse(Type::isBlank([]));
    }

    /**
     * 可调用结构判定。
     */
    public function testIsCallable(): void
    {
        self::assertTrue(Type::isCallable('strlen'));
        self::assertTrue(Type::isCallable(static fn (): int => 1));
        self::assertFalse(Type::isCallable('fxri_not_a_function'));
    }

    /**
     * 邮箱、网址与邮编判定。
     */
    public function testFormatChecks(): void
    {
        self::assertTrue(Type::isEmail('dev@fxri.net'));
        self::assertFalse(Type::isEmail('dev@'));
        self::assertTrue(Type::isUrl('https://fxri.net'));
        self::assertFalse(Type::isUrl('fxri.net'));
        self::assertTrue(Type::isZipcode('100000'));
        self::assertFalse(Type::isZipcode('010000'));
        self::assertFalse(Type::isZipcode('10000'));
    }

    /**
     * IP 族判定按版本区分。
     */
    public function testIpChecks(): void
    {
        self::assertTrue(Type::isIp('192.168.0.1'));
        self::assertTrue(Type::isIp('2001:db8::1'));
        self::assertFalse(Type::isIp('999.1.1.1'));
        self::assertTrue(Type::isIpv4('192.168.0.1'));
        self::assertFalse(Type::isIpv4('::1'));
        self::assertTrue(Type::isIpv6('::1'));
        self::assertFalse(Type::isIpv6('192.168.0.1'));
    }

    /**
     * JSON 判定只认数组与对象结构。
     */
    public function testIsJson(): void
    {
        self::assertTrue(Type::isJson('{"a":1}'));
        self::assertTrue(Type::isJson('[1,2]'));
        self::assertFalse(Type::isJson('1'));
        self::assertFalse(Type::isJson(''));
        self::assertFalse(Type::isJson('{bad}'));
    }

    /**
     * 手机号与时间格式判定。
     */
    public function testMobileAndTime(): void
    {
        self::assertTrue(Type::isMobile('13800138000'));
        self::assertFalse(Type::isMobile('23800138000'));
        self::assertFalse(Type::isMobile('1380013800'));
        self::assertTrue(Type::isTime('2026-10-31 12:00:00'));
        self::assertFalse(Type::isTime('2026-10-31'));
        self::assertTrue(Type::isTime('2026-10-31', 'Y-m-d'));
        self::assertFalse(Type::isTime('2026-02-30 00:00:00'));
    }

    /**
     * 布尔转换兼容常见字符串写法，其余交给原生强转。
     */
    public function testToBool(): void
    {
        foreach (['1', 'true', 'YES', 'on'] as $value) {
            self::assertTrue(Type::toBool($value), $value);
        }
        foreach (['', '0', 'false', 'no', 'off'] as $value) {
            self::assertFalse(Type::toBool($value), $value);
        }

        self::assertTrue(Type::toBool('anything'));
        self::assertFalse(Type::toBool(0));
        self::assertTrue(Type::toBool(1));
    }

    /**
     * 数值转换对非数值回落为零。
     */
    public function testNumberConversion(): void
    {
        self::assertSame(12, Type::toInt('12'));
        self::assertSame(12, Type::toInt(12.9));
        self::assertSame(0, Type::toInt('abc'));
        self::assertSame(1.5, Type::toFloat('1.5'));
        self::assertSame(0.0, Type::toFloat('abc'));
        self::assertSame(3.0, Type::toFloat(3));
    }

    /**
     * 字符串转换覆盖标量、可转字符串对象与不可表达值。
     */
    public function testToString(): void
    {
        self::assertSame('x', Type::toString('x'));
        self::assertSame('1', Type::toString(true));
        self::assertSame('0', Type::toString(false));
        self::assertSame('12', Type::toString(12));
        self::assertSame('1.5', Type::toString(1.5));
        self::assertSame('风音', Type::toString($this->stringable('风音')));
        self::assertSame('', Type::toString([]));
        self::assertSame('', Type::toString(null));
    }

    /**
     * 构造可转字符串对象。
     *
     * @param string $value 内容
     * @return Stringable 对象
     */
    private function stringable(string $value): Stringable
    {
        return new class ($value) implements Stringable {
            public function __construct(private readonly string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };
    }
}
