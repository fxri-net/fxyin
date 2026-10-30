<?php

declare(strict_types=1);

namespace Fxri\Tooling;

use InvalidArgumentException;

/**
 * 技能入口薄壳安装命令。
 *
 * 把原先 `tools/install-skills.php` 的参数与退出码逻辑收进 `src`，让随包的 `fxyin skills`
 * 在接入项目里也能直接使用；安装与校验本身仍由 `SkillInstaller` 承担。
 */
final class SkillsCommand
{
    /** 默认目标目录 */
    private const TARGETS = SkillInstaller::TARGETS;

    /**
     * 执行命令。
     *
     * @param list<string> $args 命令参数，不含命令名
     * @param string $root 项目根目录，缺省值由调用方给出
     * @return array{code: int, out: list<string>, err: list<string>} 退出码与输出行
     */
    public static function run(array $args, string $root): array
    {
        /** @var array{targets: list<string>, check: bool, root: string, help: bool} $options 选项 */
        $options = ['targets' => [], 'check' => false, 'root' => $root, 'help' => false];
        $count = count($args);
        for ($index = 0; $index < $count; ++$index) {
            $argument = $args[$index];
            if ('--check' === $argument) {
                $options['check'] = true;

                continue;
            }
            if ('--help' === $argument || '-h' === $argument) {
                $options['help'] = true;

                continue;
            }
            if ('--root' === $argument || '--target' === $argument) {
                $value = $args[$index + 1] ?? '';
                if ('' === $value || str_starts_with($value, '--')) {
                    return ['code' => 2, 'out' => [], 'err' => [sprintf('选项 %s 缺少取值', $argument)]];
                }
                ++$index;
                if ('--root' === $argument) {
                    $options['root'] = $value;
                } else {
                    $options['targets'][] = $value;
                }

                continue;
            }

            return ['code' => 2, 'out' => [], 'err' => ['未知参数：' . $argument]];
        }

        if ($options['help']) {
            return ['code' => 0, 'out' => [self::usage()], 'err' => []];
        }

        $targets = [] === $options['targets'] ? self::TARGETS : $options['targets'];
        try {
            if ($options['check']) {
                $out = [];
                $failed = false;
                foreach ($targets as $target) {
                    $passed = SkillInstaller::check($options['root'], [$target]);
                    $out[] = ($passed ? '通过：' : '不一致：') . $target;
                    $failed = $failed || !$passed;
                }
                if ($failed) {
                    return ['code' => 1, 'out' => $out, 'err' => ['薄壳与真源不一致，请重跑 php fxyin skills']];
                }

                return ['code' => 0, 'out' => $out, 'err' => []];
            }

            $result = SkillInstaller::install($options['root'], $targets);
            $out = [];
            foreach ($result['installed'] as $file) {
                $out[] = '已写入：' . $file;
            }
            foreach ($result['skipped'] as $file) {
                $out[] = '已跳过（同名文件不带本包标记，保留项目自有内容）：' . $file;
            }
            $out[] = sprintf('共写入 %d 个，跳过 %d 个', count($result['installed']), count($result['skipped']));

            return ['code' => 0, 'out' => $out, 'err' => []];
        } catch (InvalidArgumentException $exception) {
            return ['code' => 2, 'out' => [], 'err' => [$exception->getMessage()]];
        }
    }

    /**
     * 渲染命令帮助。
     *
     * @param string $binary 调用方看到的命令名，如 fxyin 或 vendor/bin/fxyin
     * @return string 帮助文本，以 LF 行尾
     */
    public static function usage(string $binary = 'fxyin'): string
    {
        $lines = [
            'php ' . $binary . ' skills [选项]',
            '',
            '选项：',
            '  --target <目录>  目标目录，相对项目根，可重复；默认 .agents/skills（Trae 用 .trae/skills）',
            '  --check          只校验薄壳与真源是否一致，不写盘；不一致以 1 退出',
            '  --root <目录>    项目根目录，默认为当前工作目录',
            '  --help, -h       显示帮助',
        ];

        return implode("\n", $lines) . "\n";
    }
}
