<?php

declare(strict_types=1);

namespace Fxri\Tests\Security;

use Fxri\Security\Hash;
use Fxri\Security\HashLength;
use PHPUnit\Framework\TestCase;

/**
 * 摘要测试：覆盖长度枚举、口令散列与定长比较。
 */
final class HashTest extends TestCase
{
    /**
     * 两种长度按约定产出。
     */
    public function testLength(): void
    {
        self::assertSame(16, strlen(Hash::md5('风音', HashLength::Short16)));
        self::assertSame(32, strlen(Hash::md5('风音', HashLength::Full32)));
    }

    /**
     * 缺省返回 32 位。
     */
    public function testDefaultLength(): void
    {
        self::assertSame(md5('风音'), Hash::md5('风音'));
    }

    /**
     * 16 位结果取自 32 位的中间一段。
     */
    public function testShortIsPartOfFull(): void
    {
        self::assertSame(substr(md5('风音'), 8, 16), Hash::md5('风音', HashLength::Short16));
    }

    /**
     * 口令散列不保存明文且可校验。
     */
    public function testPassword(): void
    {
        $hash = Hash::password('fxri-secret');

        self::assertNotSame('fxri-secret', $hash);
        self::assertTrue(Hash::verify('fxri-secret', $hash));
        self::assertFalse(Hash::verify('fxri-other', $hash));
    }

    /**
     * 定长比较按内容返回结果。
     */
    public function testEquals(): void
    {
        self::assertTrue(Hash::equals('fxri', 'fxri'));
        self::assertFalse(Hash::equals('fxri', 'fxrI'));
    }
}
