<?php

declare(strict_types=1);

namespace Fxri\Tooling;

use InvalidArgumentException;

/**
 * IDE 元数据生成与校验。
 *
 * 只产出 `.phpstorm.meta.php` 的文本，不参与运行时；PhpStorm 与 VS Code 的 Intelephense 都读取该文件，
 * 让「按字符串名称取对象」的工厂方法在补全与跳转里得到具体返回类。
 */
final class MetaGenerator
{
    /** 类名：允许 PHP 合法的 Unicode 类名，逐段以反斜杠分隔 */
    private const CLASS_NAME_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/D';

    /** 映射键：小写短名，允许点号、下划线、连字符分隔 */
    private const KEY_PATTERN = '/^[a-z][a-z0-9_.\-]*$/D';

    /** 方法名：与类名中的单段规则一致 */
    private const METHOD_PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/D';

    /**
     * 校验文件与映射是否一致。
     *
     * @param string $file 文件路径
     * @param array<string, array<string, class-string>> $registries 映射
     * @return bool 是否一致，文件不存在或读不到时返回 false
     */
    public static function check(string $file, array $registries): bool
    {
        if ('' === trim($file)) {
            throw new InvalidArgumentException('元数据文件路径不能为空');
        }

        $contents = is_file($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            return false;
        }

        // 行尾按 LF 统一后比较，避免 Windows 检出把 CRLF 误判为内容漂移
        return self::normalizeEol($contents) === self::normalizeEol(self::render($registries));
    }

    /**
     * 渲染元数据内容。
     *
     * @param array<string, array<string, class-string>> $registries 工厂方法全名 => 名称映射
     * @return string 文件内容，以 LF 行尾、单个换行结束
     */
    public static function render(array $registries): string
    {
        /** @var list<array{factory: string, map: array<string, string>}> $entries 待输出条目 */
        $entries = [];
        foreach ($registries as $factory => $mapping) {
            $entries[] = [
                'factory' => self::parseFactory($factory),
                'map' => self::normalizeMapping($mapping),
            ];
        }

        // 工厂方法按名称排序，保证同一份映射每次都渲染出相同字节；映射键保持传入顺序，便于对照配置
        usort($entries, static fn (array $left, array $right): int => strcmp($left['factory'], $right['factory']));

        $lines = ['<?php', '', 'namespace PHPSTORM_META {'];
        foreach ($entries as $entry) {
            if ([] === $entry['map']) {
                // 空映射输出单行，避免留下只有括号的空块
                $lines[] = sprintf('    override(%s(0), map([]));', $entry['factory']);

                continue;
            }

            $lines[] = sprintf('    override(%s(0), map([', $entry['factory']);
            foreach ($entry['map'] as $key => $class) {
                $lines[] = sprintf("        '%s' => \\%s::class,", $key, $class);
            }
            $lines[] = '    ]));';
        }
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    /**
     * 校验并归一化类名。
     *
     * @param string $class 类名，可带前导反斜杠
     * @return string 去掉前导反斜杠的类名
     */
    private static function normalizeClass(string $class): string
    {
        $class = ltrim(trim($class), '\\');
        if (1 !== preg_match(self::CLASS_NAME_PATTERN, $class)) {
            throw new InvalidArgumentException('类名格式不合法：' . $class);
        }

        return $class;
    }

    /**
     * 归一行尾为 LF。
     *
     * @param string $contents 文件内容
     * @return string 归一后的内容
     */
    private static function normalizeEol(string $contents): string
    {
        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * 校验并归一化映射。
     *
     * @param array<string, class-string> $mapping 名称 => 类名
     * @return array<string, string> 名称 => 去掉前导反斜杠的类名
     */
    private static function normalizeMapping(array $mapping): array
    {
        $normalized = [];
        foreach ($mapping as $key => $class) {
            if (1 !== preg_match(self::KEY_PATTERN, $key)) {
                throw new InvalidArgumentException('映射键须为非空的小写短名：' . $key);
            }
            $normalized[$key] = self::normalizeClass($class);
        }

        return $normalized;
    }

    /**
     * 校验并归一化工厂方法全名。
     *
     * @param string $factory 工厂方法全名，形如 类名::方法名
     * @return string 补上前导反斜杠的全名
     */
    private static function parseFactory(string $factory): string
    {
        $factory = trim($factory);
        if ('' === $factory) {
            throw new InvalidArgumentException('工厂方法全名不能为空');
        }

        $segments = explode('::', $factory);
        if (2 !== count($segments)) {
            throw new InvalidArgumentException('工厂方法全名须为 类名::方法名 形式：' . $factory);
        }

        [$class, $method] = $segments;
        if (1 !== preg_match(self::METHOD_PATTERN, $method)) {
            throw new InvalidArgumentException('工厂方法名不合法：' . $factory);
        }

        return '\\' . self::normalizeClass($class) . '::' . $method;
    }
}
