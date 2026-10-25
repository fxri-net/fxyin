<?php

declare(strict_types=1);

/**
 * 数字码扫描器。
 *
 * 把「不得使用数字码」固化为机检规则，扫描目录下全部 PHP 文件，命中任一规则即非零退出：
 * 1. numeric-case：switch 分支不得使用数字字面量（case 1:）
 * 2. dotted-literal：不得出现 1.1.2 这类点分数字字面量串
 * 3. numeric-mode-param：公共方法不得以 int $mode/$type/$style/$case 收参来选择行为
 *
 * Integration 目录列入白名单：转译外部协议时可能必须读取对方规定的数字码。
 *
 * 用法：php tools/no-numeric-code.php [扫描目录]
 */

/** 白名单目录名，命中即整目录跳过 */
const NO_NUMERIC_WHITELIST = 'Integration';

/**
 * 列出目录下全部 PHP 文件。
 *
 * @param string $root 扫描根目录
 * @return list<string> 归一为正斜杠的文件路径
 */
function listPhpFiles(string $root): array
{
    $filter = new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        static fn (SplFileInfo $current): bool => !$current->isDir() || NO_NUMERIC_WHITELIST !== $current->getFilename()
    );

    $files = [];
    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file instanceof SplFileInfo && 'php' === $file->getExtension()) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    return $files;
}

/**
 * 扫描单个文件的代码内容。
 *
 * @param string $path 文件路径，仅用于输出定位
 * @param string $code 文件内容
 * @return list<string> 违规描述，空数组表示通过
 */
function scanCode(string $path, string $code): array
{
    /** @var array<string, array{0: string, 1: string}> 逐行规则：规则名 => [正则, 说明] */
    $lineRules = [
        'numeric-case' => ['/\bcase\s+-?\d+\s*:/', '禁用数字分支，改用具名枚举'],
        'dotted-literal' => ['/([\'"])-?\d+\.\d+(?:\.\d+)?\1/', '禁用点分数字串，改用语义方法或具名枚举'],
    ];

    $violations = [];

    $lines = preg_split('/\R/', $code);
    foreach (false === $lines ? [] : $lines as $index => $line) {
        foreach ($lineRules as $name => [$pattern, $remark]) {
            if (1 === preg_match($pattern, $line)) {
                $violations[] = sprintf('%s:%d [%s] %s', $path, $index + 1, $name, $remark);
            }
        }
    }

    // 方法签名可能跨行，单独按整体匹配后再折算行号
    $found = preg_match_all(
        '/(?:(public|protected|private)\s+)?(?:static\s+)?function\s+(\w+)\s*\(([\s\S]*?)\)\s*(?::[^;{]*)?[;{]/',
        $code,
        $matches,
        PREG_SET_ORDER | PREG_OFFSET_CAPTURE
    );
    foreach (false === $found ? [] : $matches as $match) {
        $visibility = '' === $match[1][0] ? 'public' : $match[1][0];
        if ('public' !== $visibility) {
            continue;
        }
        if (1 !== preg_match('/\bint\s+\$(?:mode|type|style|case)\b/', $match[3][0])) {
            continue;
        }
        $line = substr_count(substr($code, 0, $match[0][1]), "\n") + 1;
        $violations[] = sprintf('%s:%d [numeric-mode-param] 方法 %s() 以数字码选择行为，请改用具名枚举', $path, $line, $match[2][0]);
    }

    return $violations;
}

$root = $argv[1] ?? 'src';
if (!is_dir($root)) {
    fwrite(STDERR, sprintf("扫描目录不存在：%s\n", $root));

    exit(2);
}

$violations = [];
foreach (listPhpFiles($root) as $file) {
    $code = file_get_contents($file);
    $violations = array_merge($violations, scanCode($file, false === $code ? '' : $code));
}

if ([] === $violations) {
    echo '数字码扫描通过：', $root, PHP_EOL;

    exit(0);
}

echo implode(PHP_EOL, $violations), PHP_EOL;

exit(1);
