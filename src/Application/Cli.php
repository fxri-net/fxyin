<?php

declare(strict_types=1);

namespace Fxri\Application;

use Fxri\Config\Config;
use Fxri\Config\Path;
use Fxri\Config\Store;
use Fxri\Param\Merger;
use Fxri\Support\Type;

/**
 * 命令行入参。
 *
 * 解析 `@a.b=值` 形式的参数并给出运行环境兜底值，承接 1.x 门面 `Param::init()`
 * 与 `Server::env()` 的能力；只在显式开启时生效，不隐式改写全局变量。
 */
final class Cli
{
    /** 参数前缀 */
    private const PREFIX = '@';

    /**
     * 读取当前进程参数并解析。
     *
     * @return array{arguments: list<string>, values: array<array-key, mixed>} 剩余参数与嵌套值
     */
    public static function fromGlobals(): array
    {
        $arguments = $_SERVER['argv'] ?? [];

        return self::parse(is_array($arguments) ? $arguments : []);
    }

    /**
     * 解析命令行参数。
     *
     * 以 `@` 开头的参数按点号路径写入嵌套数组，`/_` 片段还原为键名中的点；
     * 其余参数原样保留并重新编号，已被消费的参数不再出现在结果里。
     *
     * @param array<array-key, mixed> $arguments 原始参数
     * @return array{arguments: list<string>, values: array<array-key, mixed>} 剩余参数与嵌套值
     */
    public static function parse(array $arguments): array
    {
        $rest = [];
        $values = [];

        foreach ($arguments as $argument) {
            $value = Type::toString($argument);
            if (!str_starts_with($value, self::PREFIX)) {
                $rest[] = $value;

                continue;
            }

            $parts = explode('=', substr($value, strlen(self::PREFIX)), 2);
            $values = Merger::merge($values, Path::nest($parts[0], $parts[1] ?? null));
        }

        return ['arguments' => $rest, 'values' => $values];
    }

    /**
     * 从配置取命令行运行环境的兜底值。
     *
     * 只下发非空的 `app.param.cli.svr_name` 与 `svr_uri`，供 `ServerContext` 在
     * 变量缺失时兜底，不覆盖真实变量。
     *
     * @param Config|Store $config 配置
     * @return array<string, string> 服务端变量兜底值
     */
    public static function serverDefaults(Config|Store $config): array
    {
        $defaults = [];

        $host = $config->get('app.param.cli.svr_name');
        if (is_string($host) && '' !== $host) {
            $defaults['SERVER_NAME'] = strtolower($host);
        }

        $uri = $config->get('app.param.cli.svr_uri');
        if (is_string($uri) && '' !== $uri) {
            $defaults['REQUEST_URI'] = strtolower($uri);
        }

        return $defaults;
    }
}
