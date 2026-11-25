<?php

declare(strict_types=1);

namespace Fxri\Tooling;

use Fxri\File\Filesystem;
use InvalidArgumentException;
use RuntimeException;

/**
 * 技能入口薄壳的安装与校验。
 *
 * 技能真源随 Composer 包落在 `vendor/fxri/fxyin/skills/`，agent 不会扫描 vendor 目录；
 * 本类在项目面目标目录写入同名薄壳，只含 name、description 与真源位置，不复制正文。
 * 薄壳带固定标记，重复安装只覆盖本包生成的内容，项目自有的同名技能保持原样。
 */
final class SkillInstaller
{
    /** 薄壳标记版本：模板正文变更抬次版本，结构或协议变更抬主版本 */
    public const MARKER_VERSION = 'v1.1';

    /** 薄壳标记：识别本包生成的入口 */
    public const MARKER = '<!-- fxyin-skill-entry: ' . self::MARKER_VERSION . ' -->';

    /** 标记解析：兼容历史单段标记（v1 按 v1.0 处理） */
    private const MARKER_PATTERN = '/<!--\s*fxyin-skill-entry:\s*v(\d+(?:\.\d+)?)\s*-->/';

    /** 默认目标目录：相对项目根 */
    public const TARGETS = ['.agents/skills'];

    /**
     * 校验目标目录里的薄壳是否与当前真源一致。
     *
     * @param string $projectRoot 项目根目录
     * @param list<string> $targets 相对项目根的目标目录
     * @return bool 全部存在且内容一致返回 true
     */
    public static function check(string $projectRoot, array $targets = self::TARGETS): bool
    {
        return [] === self::issues($projectRoot, $targets);
    }

    /**
     * 列出薄壳与真源不一致的明细，供校验命令逐条输出。
     *
     * @param string $projectRoot 项目根目录
     * @param list<string> $targets 相对项目根的目标目录
     * @return list<array{file: string, kind: string, found: string}> 明细；kind 取 missing / marker / version / body
     */
    public static function issues(string $projectRoot, array $targets = self::TARGETS): array
    {
        $root = self::normalizeRoot($projectRoot);
        $issues = [];
        foreach ($targets as $target) {
            $directory = self::targetDirectory($root, $target);
            foreach (self::packageSkills() as $skill) {
                $file = $directory . DIRECTORY_SEPARATOR . $skill['name'] . DIRECTORY_SEPARATOR . 'SKILL.md';
                if (!is_file($file)) {
                    $issues[] = ['file' => $file, 'kind' => 'missing', 'found' => ''];

                    continue;
                }

                $contents = Filesystem::read($file);
                $version = self::version($contents);
                if (null === $version) {
                    $issues[] = ['file' => $file, 'kind' => 'marker', 'found' => ''];

                    continue;
                }

                if (0 !== self::compareVersion($version)) {
                    $issues[] = ['file' => $file, 'kind' => 'version', 'found' => self::versionText($version)];

                    continue;
                }

                $expected = self::render($skill['name'], $skill['description'], self::relativePath($root, $skill['source']));
                if (LineEnding::normalize($contents) !== LineEnding::normalize($expected)) {
                    // 标记一致但正文漂移：文案改过未抬版本时才会出现
                    $issues[] = ['file' => $file, 'kind' => 'body', 'found' => ''];
                }
            }
        }

        return $issues;
    }

    /**
     * 判断内容是否为本包生成的薄壳（含历史版本标记）。
     *
     * @param string $contents 文件内容
     * @return bool 带本包标记返回 true
     */
    public static function owned(string $contents): bool
    {
        return 1 === preg_match(self::MARKER_PATTERN, $contents);
    }

    /**
     * 读取薄壳标记版本。
     *
     * @param string $contents 文件内容
     * @return array{major: int, minor: int}|null 无标记返回 null
     */
    public static function version(string $contents): ?array
    {
        if (1 !== preg_match(self::MARKER_PATTERN, $contents, $matches)) {
            return null;
        }

        $parts = explode('.', $matches[1]);

        return ['major' => (int) $parts[0], 'minor' => (int) ($parts[1] ?? 0)];
    }

    /**
     * 在目标目录安装薄壳。
     *
     * @param string $projectRoot 项目根目录
     * @param list<string> $targets 相对项目根的目标目录
     * @return array{installed: list<string>, skipped: list<string>} 写入与跳过的文件
     */
    public static function install(string $projectRoot, array $targets = self::TARGETS): array
    {
        $root = self::normalizeRoot($projectRoot);
        $installed = [];
        $skipped = [];
        foreach ($targets as $target) {
            $directory = self::targetDirectory($root, $target);
            foreach (self::packageSkills() as $skill) {
                $file = $directory . DIRECTORY_SEPARATOR . $skill['name'] . DIRECTORY_SEPARATOR . 'SKILL.md';
                // 同名文件不带本包标记时视为项目自有技能，不覆盖；历史单段标记仍识别为本包产物
                if (is_file($file) && !self::owned(Filesystem::read($file))) {
                    $skipped[] = $file;

                    continue;
                }

                Filesystem::write($file, self::render($skill['name'], $skill['description'], self::relativePath($root, $skill['source'])));
                $installed[] = $file;
            }
        }

        return ['installed' => $installed, 'skipped' => $skipped];
    }

    /**
     * 渲染单个技能的入口薄壳。
     *
     * @param string $name 技能名
     * @param string $description 技能描述，用于触发匹配
     * @param string $source 真源 SKILL.md 相对项目根的路径
     * @return string 薄壳内容，以 LF 行尾、单个换行结束
     */
    public static function render(string $name, string $description, string $source): string
    {
        $lines = [
            '---',
            'name: ' . trim($name),
            'description: ' . trim($description),
            '---',
            '',
            self::MARKER,
            '<!-- 由 Fxri\Tooling\SkillInstaller 生成，可安全删除；需要时执行 php vendor/bin/fxyin skills 重新生成 -->',
            '本技能只是入口，不承载条文：真源在 `' . $source . '`，同目录的 `references/` 为补充材料；内容以真源为准。',
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * 标记版本的展示文本（固定两段式）。
     *
     * @param array{major: int, minor: int} $version 版本
     * @return string 形如 v1.1
     */
    private static function versionText(array $version): string
    {
        return sprintf('v%d.%d', $version['major'], $version['minor']);
    }

    /**
     * 比较壳标记版本与当前版本。
     *
     * @param array{major: int, minor: int} $version 壳标记版本
     * @return int 小于 0 表示落后，0 表示一致，大于 0 表示更新
     */
    private static function compareVersion(array $version): int
    {
        [$major, $minor] = self::currentVersion();
        if ($version['major'] !== $major) {
            return $version['major'] <=> $major;
        }

        return $version['minor'] <=> $minor;
    }

    /**
     * 当前标记版本的主次号。
     *
     * @return array{int, int} 主版本与次版本
     */
    private static function currentVersion(): array
    {
        $parts = explode('.', substr(self::MARKER_VERSION, 1));

        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * 归一项目根目录并校验非空。
     *
     * @param string $projectRoot 项目根目录
     * @return string 去掉结尾分隔符的绝对路径
     */
    private static function normalizeRoot(string $projectRoot): string
    {
        $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($projectRoot)), DIRECTORY_SEPARATOR);
        if ('' === $root) {
            throw new InvalidArgumentException('项目根目录不能为空');
        }

        return $root;
    }

    /**
     * 读取包内技能真源清单。
     *
     * @return list<array{name: string, description: string, source: string}> 技能清单，按目录名排序
     */
    private static function packageSkills(): array
    {
        $directory = self::skillsDirectory();
        $names = is_dir($directory) ? scandir($directory) : false;
        if (false === $names) {
            throw new RuntimeException('无法读取技能真源目录：' . $directory);
        }

        $skills = [];
        foreach ($names as $name) {
            $file = $directory . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'SKILL.md';
            if ('.' === $name || '..' === $name || !is_file($file)) {
                continue;
            }

            $meta = self::parseFrontmatter(Filesystem::read($file));
            if ('' === $meta['name'] || '' === $meta['description']) {
                throw new InvalidArgumentException('技能缺少 name 或 description：' . $file);
            }

            $skills[] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'source' => $file,
            ];
        }

        return $skills;
    }

    /**
     * 解析 SKILL.md 的 frontmatter，只取薄壳需要的 name 与 description。
     *
     * @param string $contents 文件内容
     * @return array{name: string, description: string} 名称与描述，缺失时为空串
     */
    private static function parseFrontmatter(string $contents): array
    {
        $meta = ['name' => '', 'description' => ''];
        if (1 !== preg_match('/^---\R([\s\S]*?)\R---(?:\R|$)/', LineEnding::normalize($contents), $matches)) {
            return $meta;
        }

        foreach (explode("\n", $matches[1]) as $line) {
            $pair = explode(':', $line, 2);
            if (2 !== count($pair)) {
                continue;
            }

            $key = trim($pair[0]);
            if (isset($meta[$key]) && '' === $meta[$key]) {
                $meta[$key] = trim(trim($pair[1]), '"\'');
            }
        }

        return $meta;
    }

    /**
     * 计算真源相对项目根的路径。
     *
     * @param string $root 项目根目录
     * @param string $path 真源实际路径
     * @return string 统一正斜杠的相对路径；不在项目根下时退回实际路径
     */
    private static function relativePath(string $root, string $path): string
    {
        $root = str_replace('\\', '/', $root);
        $path = str_replace('\\', '/', $path);
        $prefix = rtrim($root, '/') . '/';

        // 真源通常落在 vendor 之内，用相对路径保证薄壳可移植；符号链接等情况退回实际路径
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    /**
     * 包内技能真源目录。
     *
     * @return string 位于包根的 skills 目录
     */
    private static function skillsDirectory(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'skills';
    }

    /**
     * 归一目标目录并校验其为项目根下的相对路径。
     *
     * @param string $root 项目根目录
     * @param string $target 相对项目根的目标目录
     * @return string 目标目录实际路径
     */
    private static function targetDirectory(string $root, string $target): string
    {
        $normalized = str_replace('\\', '/', trim($target));
        // 绝对路径、盘符与 .. 段都指向项目外，薄壳必须留在项目内
        if (str_contains($normalized, ':')) {
            throw new InvalidArgumentException('目标目录须为相对路径：' . $target);
        }

        foreach (explode('/', $normalized) as $segment) {
            if ('' === $segment || '..' === $segment) {
                throw new InvalidArgumentException('目标目录须为不含 .. 的相对路径：' . $target);
            }
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }
}
