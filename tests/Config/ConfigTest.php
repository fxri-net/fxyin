<?php

declare(strict_types=1);

namespace Fxri\Tests\Config;

use Fxri\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * 配置容器测试：点号路径、缺失回退与键名转义。
 */
final class ConfigTest extends TestCase
{
    /**
     * 返回整份配置。
     */
    public function testAll(): void
    {
        $data = ['app' => ['name' => 'fxyin']];

        self::assertSame($data, Config::make($data)->all());
    }

    /**
     * 深层路径逐级读取。
     */
    public function testGetNested(): void
    {
        $config = Config::make(['app' => ['name' => 'fxyin', 'debug' => false]]);

        self::assertSame('fxyin', $config->get('app.name'));
        self::assertFalse($config->get('app.debug'));
    }

    /**
     * 路径缺失或中途不是数组时返回默认值。
     */
    public function testGetMissingReturnsDefault(): void
    {
        $config = Config::make(['app' => ['name' => 'fxyin']]);

        self::assertNull($config->get('app.missing'));
        self::assertSame('fallback', $config->get('app.missing', 'fallback'));
        self::assertSame('fallback', $config->get('app.name.value', 'fallback'));
    }

    /**
     * 路径段中的 `/_` 还原为点号。
     */
    public function testGetWithEscapedDot(): void
    {
        $config = Config::make(['log' => ['app.log' => 'storage/logs']]);

        self::assertSame('storage/logs', $config->get('log.app/_log'));
        self::assertTrue($config->has('log.app/_log'));
        self::assertFalse($config->has('log.missing'));
    }

    /**
     * 值为 null 的键仍算存在，读取时不回落到默认值。
     */
    public function testNullValueIsPresent(): void
    {
        $config = Config::make(['app' => ['key' => null]]);

        self::assertTrue($config->has('app.key'));
        self::assertNull($config->get('app.key', 'default'));
    }
}
