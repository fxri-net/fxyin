<?php

declare(strict_types=1);

/**
 * 技能入口薄壳安装器。
 *
 * 技能真源随 Composer 包落在 vendor/fxri/fxyin/skills/，agent 不会扫描 vendor；
 * 本命令在项目面目标目录写入同名薄壳指向真源，不复制正文、不写全局目录。
 *
 * 用法：php tools/install-skills.php [--target <目录> ...] [--check] [--root <目录>]
 *
 * 本脚本是仓库开发期入口，不随包分发；接入项目按 docs/skills.md 自建入口调用 SkillInstaller。
 *
 * 选项：
 *   --target <目录>  目标目录，相对项目根，可重复；默认 .agents/skills（Trae 用 .trae/skills）
 *   --check          只校验薄壳与真源是否一致，不写盘；不一致以 1 退出
 *   --root <目录>    项目根目录，默认为本仓库根
 *   --help, -h       显示帮助
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Fxri\Tooling\SkillInstaller;

/**
 * 解析命令行参数。
 *
 * @param list<string> $argv 原始参数
 * @return array{targets: list<string>, check: bool, root: string, help: bool} 解析结果，非法参数以 2 退出
 */
function parseArguments(array $argv): array
{
    $options = ['targets' => [], 'check' => false, 'root' => '', 'help' => false];
    $count = count($argv);
    for ($index = 1; $index < $count; ++$index) {
        $argument = $argv[$index];
        if ('--check' === $argument) {
            $options['check'] = true;

            continue;
        }
        if ('--help' === $argument || '-h' === $argument) {
            $options['help'] = true;

            continue;
        }
        if ('--root' === $argument || '--target' === $argument) {
            $value = $argv[$index + 1] ?? '';
            if ('' === $value || str_starts_with($value, '--')) {
                fwrite(STDERR, sprintf("选项 %s 缺少取值\n", $argument));

                exit(2);
            }
            ++$index;
            if ('--root' === $argument) {
                $options['root'] = $value;
            } else {
                $options['targets'][] = $value;
            }

            continue;
        }

        fwrite(STDERR, sprintf("未知参数：%s\n", $argument));

        exit(2);
    }

    return $options;
}

/**
 * 打印安装结果。
 *
 * @param array{installed: list<string>, skipped: list<string>} $result 安装结果
 * @return void
 */
function printReport(array $result): void
{
    foreach ($result['installed'] as $file) {
        echo '已写入：', $file, PHP_EOL;
    }
    foreach ($result['skipped'] as $file) {
        echo '已跳过（同名文件不带本包标记，保留项目自有内容）：', $file, PHP_EOL;
    }
    echo sprintf("共写入 %d 个，跳过 %d 个\n", count($result['installed']), count($result['skipped']));
}

/**
 * 打印帮助。
 *
 * @return void
 */
function usage(): void
{
    echo <<<'TEXT'
        技能入口薄壳安装器

        用法：php tools/install-skills.php [--target <目录> ...] [--check] [--root <目录>]

        选项：
          --target <目录>  目标目录，相对项目根，可重复；默认 .agents/skills（Trae 用 .trae/skills）
          --check          只校验薄壳与真源是否一致，不写盘；不一致以 1 退出
          --root <目录>    项目根目录，默认为本仓库根
          --help, -h       显示帮助

        TEXT;
}

$options = parseArguments($argv);
if ($options['help']) {
    usage();

    exit(0);
}

$root = '' === $options['root'] ? dirname(__DIR__) : $options['root'];
$targets = [] === $options['targets'] ? SkillInstaller::TARGETS : $options['targets'];

try {
    if ($options['check']) {
        $failed = false;
        foreach ($targets as $target) {
            $passed = SkillInstaller::check($root, [$target]);
            echo $passed ? '通过：' : '不一致：', $target, PHP_EOL;
            $failed = $failed || !$passed;
        }
        if ($failed) {
            fwrite(STDERR, "薄壳与真源不一致，请重跑 php tools/install-skills.php\n");

            exit(1);
        }

        exit(0);
    }

    printReport(SkillInstaller::install($root, $targets));
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");

    exit(2);
}
