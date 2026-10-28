<?php

declare(strict_types=1);

namespace Fxri\Tests\Application;

use Fxri\Application\Cli;
use Fxri\Config\Config;
use Fxri\Config\Store;
use PHPUnit\Framework\TestCase;

/**
 * 命令行入参测试：覆盖 `@` 参数解析、嵌套键与服务端兜底值。
 */
final class CliTest extends TestCase
{
    /**
     * `@` 参数构建嵌套数组，其余参数保留并重新编号。
     */
    public function testParse(): void
    {
        $parsed = Cli::parse([
            'bin.php',
            '@app.debug.switch=1',
            'run',
            '@app.param.cli.svr_name=Example.COM',
            '@flag',
        ]);

        self::assertSame(['bin.php', 'run'], $parsed['arguments']);
        self::assertSame(
            [
                'app' => [
                    'debug' => ['switch' => '1'],
                    'param' => ['cli' => ['svr_name' => 'Example.COM']],
                ],
                'flag' => null,
            ],
            $parsed['values'],
        );
    }

    /**
     * `/_` 片段还原为键名中的点，同名路径后者覆盖前者。
     */
    public function testParseRestoresDotAndOverrides(): void
    {
        $parsed = Cli::parse(['@app.a/_b=1', '@app.a/_b=2', '@app.name=first', '@app.name=second']);

        self::assertSame([], $parsed['arguments']);
        self::assertSame(['app' => ['a.b' => '2', 'name' => 'second']], $parsed['values']);
    }

    /**
     * 从配置读取主机与路径兜底值，空值不下发。
     */
    public function testServerDefaults(): void
    {
        $store = Store::make(null, [
            'app' => ['param' => ['cli' => ['svr_name' => 'Example.COM', 'svr_uri' => '/Api']]],
        ]);

        self::assertSame(
            ['SERVER_NAME' => 'example.com', 'REQUEST_URI' => '/api'],
            Cli::serverDefaults($store),
        );

        self::assertSame([], Cli::serverDefaults(Config::make()));
        self::assertSame(
            [],
            Cli::serverDefaults(Store::make(null, ['app' => ['param' => ['cli' => ['svr_name' => '', 'svr_uri' => null]]]])),
        );
    }
}
