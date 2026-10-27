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

    /**
     * 版本号与工具链清单保持一致，避免发版后两处漂移。
     */
    public function testVersionMatchesToolchain(): void
    {
        $raw = file_get_contents(dirname(__DIR__) . '/package.json');
        if (false === $raw) {
            self::fail('无法读取 package.json');
        }

        $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $version = is_array($manifest) && isset($manifest['version']) && is_string($manifest['version'])
            ? $manifest['version']
            : '';

        self::assertSame($version, Package::VERSION);
    }
}
