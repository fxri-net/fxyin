<?php

declare(strict_types=1);

namespace Fxri\Tooling;

/**
 * 开发命令行入口的注册表、参数解析与帮助渲染。
 *
 * 只产出数据与文本，不打印、不退出；打印与退出由根入口 `fxyin` 负责，方便复用与测试。
 * 命令域分两层：随包域（接入项目也能用）与本仓内部域（依赖本仓库文件，打包时被排除）。
 */
final class Cli
{
    /** 命令域：域 => 摘要、实现脚本（null 为原生实现）与是否随包 */
    public const COMMANDS = [
        'skills' => ['summary' => '安装 AI 技能入口薄壳', 'script' => null, 'shipped' => true],
        'upgrade' => ['summary' => '1.x 升级体检（profile|scan|baseline）', 'script' => 'tools/upgrade-inspect.php', 'shipped' => true],
        'scan' => ['summary' => '扫描数字码等禁用写法', 'script' => 'tools/no-numeric-code.php', 'shipped' => true],
        'package' => ['summary' => '发布包卫生检查', 'script' => 'tools/check-package.php', 'shipped' => false],
        'parity' => ['summary' => '对等矩阵审计', 'script' => 'tools/parity-audit.php', 'shipped' => false],
    ];

    /**
     * 列出当前环境可用的命令域。
     *
     * @param string $root 包根目录
     * @return array<string, string> 域 => 摘要，按注册顺序
     */
    public static function available(string $root): array
    {
        $commands = [];
        foreach (self::COMMANDS as $name => $command) {
            if (null === $command['script'] || is_file($root . '/' . $command['script'])) {
                $commands[$name] = $command['summary'];
            }
        }

        return $commands;
    }

    /**
     * 找出随包域中缺失实现文件的命令，用于拦住打包配置回退。
     *
     * @param string $root 包根目录
     * @return list<string> 缺失实现的域
     */
    public static function missingShipped(string $root): array
    {
        $missing = [];
        foreach (self::COMMANDS as $name => $command) {
            if (!$command['shipped'] || null === $command['script']) {
                continue;
            }
            if (!is_file($root . '/' . $command['script'])) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * 解析命令行参数。
     *
     * @param list<string> $argv 原始参数，含脚本名
     * @return array{action: string, command: string, topic: string, args: list<string>, error: string} 解析结果
     */
    public static function parse(array $argv): array
    {
        $result = ['action' => 'help', 'command' => '', 'topic' => '', 'args' => [], 'error' => ''];
        /** @var list<string> $arguments 去掉脚本名后的参数 */
        $arguments = array_slice($argv, 1);
        if ([] === $arguments) {
            return $result;
        }

        $first = $arguments[0];
        if (in_array($first, ['help', '-h', '--help'], true)) {
            $result['topic'] = $arguments[1] ?? '';

            return $result;
        }

        if (!isset(self::COMMANDS[$first])) {
            $result['action'] = 'error';
            $result['error'] = '未知命令：' . $first;

            return $result;
        }

        $result['action'] = 'run';
        $result['command'] = $first;
        $result['args'] = array_slice($arguments, 1);

        return $result;
    }

    /**
     * 渲染总帮助。
     *
     * @param string $binary 调用方看到的命令名，如 fxyin 或 vendor/bin/fxyin
     * @param string $root 包根目录
     * @return string 帮助文本，以 LF 行尾
     */
    public static function usage(string $binary, string $root): string
    {
        $lines = [
            $binary . ' — 风音框架命令行',
            '',
            '用法：php ' . $binary . ' <命令> [参数...]',
            '',
            '命令：',
        ];
        foreach (self::available($root) as $name => $summary) {
            $lines[] = sprintf('  %-8s %s', $name, $summary);
        }
        $lines[] = sprintf('  %-8s %s', 'help', '查看帮助；help <命令> 查看命令用法');
        $lines[] = '';
        $lines[] = '退出码：0 成功 / 1 门禁不通过 / 2 用法错误';

        return implode("\n", $lines) . "\n";
    }
}
