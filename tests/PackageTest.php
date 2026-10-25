<?php

declare(strict_types=1);

namespace Fxri\Tests;

use Fxri\Package;
use PHPUnit\Framework\TestCase;

/**
 * 骨架冒烟测试：确认自动加载、命名空间与测试链路均已就位。
 */
final class PackageTest extends TestCase
{
    /**
     * 描述信息由包名与版本号拼成。
     */
    public function testDescribe(): void
    {
        self::assertSame(Package::NAME . ' ' . Package::VERSION, Package::describe());
    }

    /**
     * 版本号落在 2.x 主线。
     */
    public function testVersionIsTwoSeries(): void
    {
        self::assertStringStartsWith('2.', Package::VERSION);
    }
}
