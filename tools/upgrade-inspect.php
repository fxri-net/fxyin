<?php

declare(strict_types=1);

/**
 * 1.x 升级体检工具。
 *
 * 对目标项目做只读画像与旧 API 统计，不 include 项目文件、不执行 Composer。
 * 子命令：
 *   profile  项目画像：PHP、宿主框架、入口、Composer、扩展模块、体量档位与阻塞项
 *   scan     旧 API 统计：\fxapp\*、$echo[n]、点分模式号、is_*、fxy_*、\fxyin\
 *   baseline 生成或校验只减不增的基线
 *
 * 用法：php tools/upgrade-inspect.php <profile|scan|baseline> --root=<项目> [--json|--markdown]
 *      php tools/upgrade-inspect.php baseline --root=<项目> --write --baseline=<文件>
 *      php tools/upgrade-inspect.php baseline --root=<项目> --check --baseline=<文件>
 */

/** 1.x 扩展配置：应用目录写在 env.base.app */
const APP_DIRECTORY_CONFIG = 'extend/fxri/data/config.php';

/** 应用目录配置缺失或无法解析时的兜底候选 */
const FALLBACK_APP_DIRECTORIES = ['app', 'application'];

/** 对等矩阵：升级建议的第二来源，避免人工表与矩阵漂移 */
const PARITY_MATRIX = 'tests/Parity/legacy-api.json';

/** 扩展门面目录：项目在 1.x 里追加门面方法的位置 */
const FACADE_EXTENSION_DIRECTORY = 'extend/fxri/data/app/controller/facade_ext';

/** 目录名排除：第三方依赖与运行产物 */
const EXCLUDED_DIRECTORIES = ['vendor', 'runtime', 'node_modules', '.git'];

/** 路径排除：1.x 框架本体与独立安全模块不属于本次迁移 */
const EXCLUDED_PATHS = ['/extend/fxri/base/', '/extend/fxri/safe/', '/extend/fxri/safe_local/'];

/** 1.x 框架门面类，用于区分项目自有扩展方法 */
const FRAMEWORK_CLASSES = ['Base', 'Client', 'Data', 'File', 'Math', 'Param', 'Safe', 'Server', 'Service', 'Text', 'View'];

/**
 * 程序入口。
 *
 * @param list<string> $argv 命令行参数
 * @return int 退出码
 */
function main(array $argv): int
{
    $command = $argv[1] ?? '';
    if (in_array($command, ['', 'help', '-h', '--help'], true)) {
        echo usage();

        return 0;
    }

    $options = parseOptions(array_slice($argv, 2));
    $rootOption = $options['root'] ?? null;
    $root = resolveRoot(is_string($rootOption) ? $rootOption : getcwd());
    if (null === $root) {
        fwrite(STDERR, '项目根目录不存在' . PHP_EOL);

        return 2;
    }

    return match ($command) {
        'profile' => outputProfile($root, $options),
        'scan' => outputScan($root, $options),
        'baseline' => runBaseline($root, $options),
        default => usageError('未知子命令：' . $command),
    };
}

/**
 * 输出使用说明。
 *
 * @return string 使用说明
 */
function usage(): string
{
    return implode(PHP_EOL, [
        '用法：',
        '  php tools/upgrade-inspect.php profile  --root=<项目> [--json|--markdown]',
        '  php tools/upgrade-inspect.php scan     --root=<项目> [--json|--markdown]',
        '  php tools/upgrade-inspect.php baseline --root=<项目> --write --baseline=<文件>',
        '  php tools/upgrade-inspect.php baseline --root=<项目> --check --baseline=<文件>',
        '',
        '说明：',
        '  工具只读，不 include 项目文件，不执行 Composer。',
        '  --root 缺省为当前目录；--json 与 --markdown 只能选一个，缺省为 Markdown。',
        '',
    ]);
}

/**
 * 解析 --key=value 形式的参数。
 *
 * @param list<string> $args 参数列表
 * @return array<string, string|bool> 参数字典
 */
function parseOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $body = substr($arg, 2);
        $position = strpos($body, '=');
        if (false === $position) {
            $options[$body] = true;

            continue;
        }
        $options[substr($body, 0, $position)] = substr($body, $position + 1);
    }

    return $options;
}

/**
 * 归一项目根目录。
 *
 * @param string|false $path 原始路径
 * @return string|null 真实路径
 */
function resolveRoot(string|false $path): ?string
{
    if (false === $path || '' === $path) {
        return null;
    }
    $real = realpath($path);

    return false === $real || !is_dir($real) ? null : $real;
}

/**
 * 输出用法错误。
 *
 * @param string $message 错误信息
 * @return int 退出码
 */
function usageError(string $message): int
{
    fwrite(STDERR, $message . PHP_EOL . PHP_EOL . usage());

    return 2;
}

/**
 * 输出项目画像。
 *
 * @param string $root 项目根
 * @param array<string, string|bool> $options 参数
 * @return int 退出码
 */
function outputProfile(string $root, array $options): int
{
    $profile = buildProfile($root);
    if (isset($options['json'])) {
        emitJson($profile);

        return 0;
    }

    echo formatProfileMarkdown($profile);

    return 0;
}

/**
 * 输出旧 API 统计。
 *
 * @param string $root 项目根
 * @param array<string, string|bool> $options 参数
 * @return int 退出码
 */
function outputScan(string $root, array $options): int
{
    $scan = scanProject($root);
    if (isset($options['json'])) {
        emitJson($scan);

        return 0;
    }

    echo formatScanMarkdown($scan);

    return 0;
}

/**
 * 生成或校验基线。
 *
 * @param string $root 项目根
 * @param array<string, string|bool> $options 参数
 * @return int 退出码
 */
function runBaseline(string $root, array $options): int
{
    $baseline = $options['baseline'] ?? null;
    if (!is_string($baseline) || '' === $baseline) {
        return usageError('baseline 需要显式 --baseline=<文件>');
    }
    if (isset($options['write'])) {
        return writeBaseline($root, $baseline);
    }
    if (isset($options['check'])) {
        return checkBaseline($root, $baseline);
    }

    return usageError('baseline 需要 --write 或 --check');
}

/**
 * 生成项目画像。
 *
 * @param string $root 项目根
 * @return array<string, mixed> 画像
 */
function buildProfile(string $root): array
{
    $composerFile = $root . '/composer.json';
    $composer = readJsonFile($composerFile);
    $composerRaw = is_file($composerFile) ? (string) file_get_contents($composerFile) : '';
    $lock = readJsonFile($root . '/composer.lock');
    $constraint = phpConstraint($composer, $composerRaw);
    $scan = scanProject($root);
    $modules = listSubdirectories($root . '/extend/fxri');
    $entry = [];
    foreach (['index.php', 'public/index.php', 'public/router.php', 'extend/fxri/bootstrap.php'] as $candidate) {
        if (is_file($root . '/' . $candidate)) {
            $entry[] = $candidate;
        }
    }

    $composerStatus = [
        'json' => is_file($composerFile),
        'lock' => is_file($root . '/composer.lock'),
        'autoload' => is_file($root . '/vendor/autoload.php'),
    ];

    $blockers = [];
    if (phpBelow82($constraint)) {
        $blockers[] = 'PHP 约束低于 8.2：' . $constraint;
    }
    if (!$composerStatus['json'] || !$composerStatus['autoload']) {
        $blockers[] = '缺少 Composer 接入，需先补 composer.json 与 vendor/autoload.php';
    }

    $notes = [];
    if (in_array('safe', $modules, true) || in_array('safe_local', $modules, true)) {
        $notes[] = '存在 safe / safe_local 模块，属独立工具，不在 fxyin 迁移范围';
    }

    $tier = tierFor($scan['counts']['fxapp']);

    return [
        'root' => $root,
        'php' => [
            'constraint' => $constraint,
            'below82' => phpBelow82($constraint),
        ],
        'framework' => detectFramework($root, $lock, $composerRaw),
        'entry' => $entry,
        'composer' => $composerStatus,
        'modules' => $modules,
        'extension' => inspectExtension($root),
        'usage' => [
            'fxapp' => $scan['counts']['fxapp'],
            'tier' => $tier,
        ],
        'blockers' => $blockers,
        'notes' => $notes,
        'recommendedRoute' => $tier,
    ];
}

/**
 * 扫描旧 API。
 *
 * @param string $root 项目根
 * @return array<string, mixed> 统计结果
 */
function scanProject(string $root): array
{
    $directories = scanDirectories($root);
    $files = collectPhpFiles($root, $directories);
    $counts = [
        'fxapp' => 0,
        'echo_indexes' => 0,
        'dot_codes' => 0,
        'is_functions' => 0,
        'fxy_functions' => 0,
        'fxyin_refs' => 0,
        'facade_extends' => 0,
        'facade_ext_refs' => 0,
        'oracle_refs' => 0,
    ];
    $byDirectory = array_fill_keys($directories, 0);
    $methods = [];

    foreach ($files as $file) {
        $code = file_get_contents($file);
        if (false === $code) {
            continue;
        }

        $counts['fxapp'] += countPattern('/\\\\fxapp\\\\([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)/', $code);
        $counts['echo_indexes'] += countPattern('/\$echo\[\d\]/', $code);
        $counts['dot_codes'] += countPattern('/([\'"])[12]\.\d(?:\.\d)?\1/', $code);
        $counts['is_functions'] += countPattern('/\bis_(?:blank|json|time|function|mobile|email|zipcode|ipv4|ipv6|url|ip)\s*\(/', $code);
        $counts['fxy_functions'] += countPattern('/\bfxy_[a-z_]+\s*\(/', $code);
        $counts['fxyin_refs'] += countPattern('/\\\\fxyin\\\\/', $code);
        $counts['facade_extends'] += countPattern('/extends\s+\\\\fxyin\\\\Facade/', $code);
        $counts['facade_ext_refs'] += countPattern('/\\\\fxapp\\\\facade_ext\\\\/', $code);
        $counts['oracle_refs'] += countPattern('/\\\\fxapp\\\\oracle\\\\/', $code);

        $relative = relativePath($root, $file);
        foreach (array_keys($byDirectory) as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory . '/')) {
                $byDirectory[$directory] += countPattern('/\\\\fxapp\\\\([A-Za-z_][A-Za-z0-9_]*)::/', $code);

                break;
            }
        }

        $matches = [];
        if (preg_match_all('/\\\\fxapp\\\\([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)/', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1] . '::' . $match[2];
                $methods[$key] = ($methods[$key] ?? 0) + 1;
            }
        }
    }

    arsort($methods);
    $extensionMethods = facadeExtensionMethods($root);
    $topMethods = [];
    foreach (array_slice($methods, 0, 20, true) as $method => $count) {
        $topMethods[] = [
            'method' => $method,
            'count' => $count,
            'suggestion' => suggestFor($method, $extensionMethods),
        ];
    }

    return [
        'root' => $root,
        'files' => count($files),
        'counts' => $counts,
        'byDirectory' => $byDirectory,
        'topMethods' => $topMethods,
    ];
}

/**
 * 生成基线数据。
 *
 * @param string $root 项目根
 * @return array<string, mixed> 基线
 */
function baselineData(string $root): array
{
    $scan = scanProject($root);

    return [
        'version' => 1,
        'generatedAt' => date('Y-m-d H:i'),
        'root' => $root,
        'directories' => $scan['byDirectory'],
        'total' => $scan['counts']['fxapp'],
    ];
}

/**
 * 写入基线文件。
 *
 * @param string $root 项目根
 * @param string $file 基线文件
 * @return int 退出码
 */
function writeBaseline(string $root, string $file): int
{
    $data = baselineData($root);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (false === $json) {
        fwrite(STDERR, '基线序列化失败' . PHP_EOL);

        return 1;
    }

    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
        fwrite(STDERR, '无法创建基线目录：' . $directory . PHP_EOL);

        return 1;
    }
    if (false === file_put_contents($file, $json . PHP_EOL)) {
        fwrite(STDERR, '无法写入基线文件：' . $file . PHP_EOL);

        return 1;
    }

    echo '基线已写入：' . $file . PHP_EOL;

    return 0;
}

/**
 * 校验基线。
 *
 * @param string $root 项目根
 * @param string $file 基线文件
 * @return int 退出码
 */
function checkBaseline(string $root, string $file): int
{
    $baseline = readJsonFile($file);
    if (null === $baseline) {
        fwrite(STDERR, '基线文件不存在或不是合法 JSON：' . $file . PHP_EOL);

        return 2;
    }
    $limits = $baseline['directories'] ?? null;
    if (!is_array($limits)) {
        fwrite(STDERR, '基线文件缺少 directories 字段' . PHP_EOL);

        return 2;
    }

    $scan = scanProject($root);
    $violations = [];
    $improvements = [];
    foreach ($limits as $directory => $limit) {
        if (!is_string($directory) || !is_numeric($limit)) {
            continue;
        }
        $current = $scan['byDirectory'][$directory] ?? 0;
        $target = (int) $limit;
        if ($current > $target) {
            $violations[] = sprintf('%s：当前 %d 处，基线 %d 处', $directory, $current, $target);
        }
        if ($current < $target) {
            $improvements[] = sprintf('%s：已降至 %d 处，可下调基线 %d 处', $directory, $current, $target);
        }
    }

    if ([] !== $violations) {
        fwrite(STDERR, '旧 API 超出基线：' . PHP_EOL);
        foreach ($violations as $violation) {
            fwrite(STDERR, '  - ' . $violation . PHP_EOL);
        }

        return 1;
    }

    foreach ($improvements as $improvement) {
        echo '提示：' . $improvement . PHP_EOL;
    }
    echo '基线校验通过：' . $file . PHP_EOL;

    return 0;
}

/**
 * 读取 JSON 文件，容忍 UTF-8 BOM。
 *
 * @param string $file 文件
 * @return array<string, mixed>|null 解析结果
 */
function readJsonFile(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $raw = file_get_contents($file);
    if (false === $raw) {
        return null;
    }
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $data = json_decode($raw, true);

    return is_array($data) ? $data : null;
}

/**
 * 取 PHP 版本约束。
 *
 * @param array<string, mixed>|null $composer composer.json 内容
 * @param string $raw 原文，用于容错解析
 * @return string 版本约束
 */
function phpConstraint(?array $composer, string $raw): string
{
    if (is_array($composer) && isset($composer['require']) && is_array($composer['require'])) {
        $php = $composer['require']['php'] ?? null;
        if (is_string($php)) {
            return $php;
        }
    }
    if (preg_match('/"php"\s*:\s*"([^"]+)"/', $raw, $matches)) {
        return $matches[1];
    }

    return '';
}

/**
 * 判断 PHP 约束是否低于 8.2。
 *
 * @param string $constraint 版本约束
 * @return bool 是否低于 8.2
 */
function phpBelow82(string $constraint): bool
{
    if ('' === $constraint) {
        return false;
    }
    if (!preg_match('/(\d+)(?:\.(\d+))?/', $constraint, $matches)) {
        return false;
    }
    $major = (int) $matches[1];
    $minor = (int) ($matches[2] ?? 0);

    return $major < 8 || (8 === $major && $minor < 2);
}

/**
 * 识别宿主框架。
 *
 * @param string $root 项目根
 * @param array<string, mixed>|null $lock composer.lock 内容
 * @param string $composerRaw composer.json 原文
 * @return string 框架描述
 */
function detectFramework(string $root, ?array $lock, string $composerRaw): string
{
    if (is_array($lock) && isset($lock['packages']) && is_array($lock['packages'])) {
        foreach ($lock['packages'] as $package) {
            if (!is_array($package) || 'topthink/framework' !== ($package['name'] ?? '')) {
                continue;
            }
            $version = $package['version'] ?? null;
            if (is_string($version)) {
                return 'ThinkPHP ' . $version;
            }
        }
    }
    if (preg_match('/"name"\s*:\s*"topthink\/framework"[\s\S]{0,200}?"version"\s*:\s*"([^"]+)"/', $composerRaw, $matches)) {
        return 'ThinkPHP ' . $matches[1];
    }
    if (is_file($root . '/core/start.php')) {
        return '非 ThinkPHP（core/start.php）';
    }
    if (is_file($root . '/think')) {
        return 'ThinkPHP（未锁定版本）';
    }
    if (is_dir($root . '/thinkphp')) {
        return 'ThinkPHP（thinkphp 目录）';
    }
    if (is_dir($root . '/vendor/topthink/framework')) {
        return 'ThinkPHP（vendor 目录）';
    }

    return '未识别';
}

/**
 * 盘点扩展目录。
 *
 * @param string $root 项目根
 * @return array<string, mixed> 扩展目录画像
 */
function inspectExtension(string $root): array
{
    $app = $root . '/extend/fxri/data/app';
    $controller = $app . '/controller';
    $storeFile = $app . '/store/common.php';
    $storeCode = is_file($storeFile) ? (string) file_get_contents($storeFile) : '';

    $facadeExtends = 0;
    foreach (directPhpFiles($controller) as $file) {
        $code = file_get_contents($file);
        if (false !== $code) {
            $facadeExtends += countPattern('/extends\s+\\\\fxyin\\\\Facade/', $code);
        }
    }

    return [
        'config' => count(directPhpFiles($app . '/config')),
        'controller' => count(directPhpFiles($controller)),
        'facade_ext' => count(directPhpFiles($controller . '/facade_ext')),
        'oracle' => count(directPhpFiles($controller . '/oracle')),
        'function' => count(directPhpFiles($app . '/function')),
        'language' => count(recursivePhpFiles($app . '/language')),
        'store' => count(directPhpFiles($app . '/store')),
        'facade_extends' => $facadeExtends,
        'store_hooks' => array_values(array_filter([
            str_contains($storeCode, 'Env::init') ? 'Env::init' : '',
            str_contains($storeCode, 'Server::branch') ? 'Server::branch' : '',
        ])),
        'exception_handler' => is_file($controller . '/think/Exception.php'),
    ];
}

/**
 * 收集扫描范围内的 PHP 文件。
 *
 * @param string $root 项目根
 * @param list<string> $directories 相对目录
 * @return list<string> 文件路径
 */
function collectPhpFiles(string $root, array $directories): array
{
    $files = [];
    foreach ($directories as $directory) {
        $path = $root . '/' . $directory;
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $file): bool {
                    $path = str_replace('\\', '/', $file->getPathname());
                    foreach (EXCLUDED_PATHS as $excluded) {
                        if (str_contains($path . '/', $excluded)) {
                            return false;
                        }
                    }
                    if ($file->isDir()) {
                        return !in_array($file->getFilename(), EXCLUDED_DIRECTORIES, true);
                    }

                    return 'php' === $file->getExtension();
                }
            )
        );
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo) {
                $files[] = str_replace('\\', '/', $file->getPathname());
            }
        }
    }
    sort($files);

    return $files;
}

/**
 * 列出目录下直接的 PHP 文件。
 *
 * @param string $path 目录
 * @return list<string> 文件路径
 */
function directPhpFiles(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }
    $files = [];
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot() || !$item->isFile() || 'php' !== $item->getExtension()) {
            continue;
        }
        $files[] = str_replace('\\', '/', $item->getPathname());
    }
    sort($files);

    return $files;
}

/**
 * 递归列出 PHP 文件。
 *
 * @param string $path 目录
 * @return list<string> 文件路径
 */
function recursivePhpFiles(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $file): bool => $file->isDir() || 'php' === $file->getExtension()
        )
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($files);

    return $files;
}

/**
 * 列出子目录名。
 *
 * @param string $path 目录
 * @return list<string> 子目录名
 */
function listSubdirectories(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }
    $names = [];
    foreach (new DirectoryIterator($path) as $item) {
        if ($item->isDot() || !$item->isDir()) {
            continue;
        }
        $names[] = $item->getFilename();
    }
    sort($names);

    return $names;
}

/**
 * 解析项目声明的应用目录。
 *
 * 1.x 在 `extend/fxri/data/config.php` 的 `env.base.app` 里声明应用目录，
 * 取值可能是 `application`、`app` 或其它名称，因此不按目录名硬编码。
 * 只做静态解析，不 include 项目文件。
 *
 * @param string $root 项目根
 * @return string|null 相对项目根的应用目录
 */
function resolveAppDirectory(string $root): ?string
{
    $file = $root . '/' . APP_DIRECTORY_CONFIG;
    if (!is_file($file)) {
        return null;
    }
    $code = file_get_contents($file);
    if (false === $code) {
        return null;
    }

    $tokens = significantTokens(token_get_all($code));
    $base = valueTokens($tokens, 'base');
    if (null === $base) {
        return null;
    }
    $app = valueTokens($base, 'app');
    if (null === $app) {
        return null;
    }

    $path = evaluatePathTokens($app, $root, dirname($file));

    return null === $path ? null : relativeTo($root, $path);
}

/**
 * 确定扫描目录。
 *
 * 应用目录取自项目配置；配置缺失或无法静态解析时才按存在性兜底探测常见命名。
 *
 * @param string $root 项目根
 * @return list<string> 相对项目根的目录列表
 */
function scanDirectories(string $root): array
{
    $directories = [];
    $app = resolveAppDirectory($root);
    if (null !== $app && is_dir($root . '/' . $app)) {
        $directories[] = $app;
    } else {
        foreach (FALLBACK_APP_DIRECTORIES as $candidate) {
            if (is_dir($root . '/' . $candidate)) {
                $directories[] = $candidate;
            }
        }
    }
    $directories[] = 'extend/fxri/data';
    $directories[] = 'public';

    return $directories;
}

/**
 * 去掉空白与注释，保留有意义的标记。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 原始标记
 * @return array<int, array{0: int, 1: string, 2: int}|string> 精简标记
 */
function significantTokens(array $tokens): array
{
    $significant = [];
    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $significant[] = $token;
    }

    return $significant;
}

/**
 * 按数组键取出值标记。
 *
 * 值可能是数组字面量或标量表达式；数组字面量会去掉最外层方括号。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 精简标记
 * @param string $key 数组键名
 * @return array<int, array{0: int, 1: string, 2: int}|string>|null 值标记
 */
function valueTokens(array $tokens, string $key): ?array
{
    $count = count($tokens);
    for ($i = 0; $i < $count; ++$i) {
        $token = $tokens[$i];
        if (!is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0] || literalValue($token[1]) !== $key) {
            continue;
        }
        $arrow = $tokens[$i + 1] ?? null;
        if (!is_array($arrow) || T_DOUBLE_ARROW !== $arrow[0]) {
            continue;
        }

        $value = [];
        $depth = 0;
        for ($j = $i + 2; $j < $count; ++$j) {
            $current = $tokens[$j];
            $text = is_array($current) ? $current[1] : $current;
            if (',' === $text && 0 === $depth) {
                break;
            }
            if (']' === $text || ')' === $text) {
                if (0 === $depth) {
                    break;
                }
                --$depth;
                $value[] = $current;

                continue;
            }
            if ('[' === $text || '(' === $text) {
                ++$depth;
            }
            $value[] = $current;
        }

        return stripOuterBrackets($value);
    }

    return null;
}

/**
 * 去掉数组字面量最外层的一对方括号。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 值标记
 * @return array<int, array{0: int, 1: string, 2: int}|string> 内层标记
 */
function stripOuterBrackets(array $tokens): array
{
    $count = count($tokens);
    if ($count >= 2 && '[' === $tokens[0] && ']' === $tokens[$count - 1]) {
        return array_slice($tokens, 1, -1);
    }

    return $tokens;
}

/**
 * 静态求值路径表达式。
 *
 * 只支持 1.x 扩展配置里的常见写法：字符串字面量、`.` 拼接、`$root`、`__DIR__` 与
 * `DIRECTORY_SEPARATOR`；遇到其它写法返回 null，交由调用方兜底。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 表达式标记
 * @param string $root 项目根
 * @param string $configDir 配置文件所在目录
 * @return string|null 绝对路径
 */
function evaluatePathTokens(array $tokens, string $root, string $configDir): ?string
{
    $value = '';
    foreach ($tokens as $token) {
        if (is_string($token)) {
            if ('.' !== $token) {
                return null;
            }

            continue;
        }
        switch ($token[0]) {
            case T_CONSTANT_ENCAPSED_STRING:
                $value .= literalValue($token[1]);

                break;
            case T_DIR:
                $value .= $configDir;

                break;
            case T_VARIABLE:
                if ('$root' !== $token[1]) {
                    return null;
                }
                // 约定：配置里的 $root 即项目根，且已带尾部分隔符
                $value .= rtrim($root, '/') . '/';

                break;
            case T_STRING:
                if ('DIRECTORY_SEPARATOR' !== $token[1]) {
                    return null;
                }
                $value .= '/';

                break;
            default:
                return null;
        }
    }

    return '' === $value ? null : $value;
}

/**
 * 还原字符串字面量的值。
 *
 * @param string $raw 带引号的字面量
 * @return string 字面量内容
 */
function literalValue(string $raw): string
{
    $body = substr($raw, 1, -1);

    return "'" === ($raw[0] ?? '') ? str_replace(['\\\\', "\\'"], ['\\', "'"], $body) : stripcslashes($body);
}

/**
 * 取相对项目根的路径。
 *
 * @param string $root 项目根
 * @param string $path 绝对路径
 * @return string|null 相对路径
 */
function relativeTo(string $root, string $path): ?string
{
    $base = rtrim(collapseSlashes(str_replace('\\', '/', (string) realpath($root))), '/');
    $normalized = rtrim(collapseSlashes(str_replace('\\', '/', $path)), '/');
    if ('' === $base || !str_starts_with($normalized, $base . '/')) {
        return null;
    }
    $relative = substr($normalized, strlen($base) + 1);

    return '' === $relative ? null : $relative;
}

/**
 * 合并重复的斜杠。
 *
 * @param string $path 路径
 * @return string 规范化路径
 */
function collapseSlashes(string $path): string
{
    return (string) preg_replace('#/+#', '/', $path);
}

/**
 * 统计正则命中次数。
 *
 * @param string $pattern 正则
 * @param string $code 代码
 * @return int 次数
 */
function countPattern(string $pattern, string $code): int
{
    $count = preg_match_all($pattern, $code);

    return false === $count ? 0 : $count;
}

/**
 * 取相对项目根的路径。
 *
 * @param string $root 项目根
 * @param string $path 绝对路径
 * @return string 相对路径
 */
function relativePath(string $root, string $path): string
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $path = str_replace('\\', '/', $path);
    if (str_starts_with($path, $root . '/')) {
        return substr($path, strlen($root) + 1);
    }

    return $path;
}

/**
 * 按调用量给出体量档位。
 *
 * @param int $count 旧门面调用数
 * @return string S / M / L
 */
function tierFor(int $count): string
{
    if ($count < 100) {
        return 'S';
    }

    return $count <= 500 ? 'M' : 'L';
}

/**
 * 收集项目扩展方法。
 *
 * 扩展门面以文件名为门面类，方法在文件内以 `function` 声明；用于把项目自有方法
 * 与框架 API 区分开，避免给出「见 2.0 API 对照」的误导建议。
 *
 * @param string $root 项目根
 * @return array<string, true> 形如 `Safe::rsapriExt` 的集合
 */
function facadeExtensionMethods(string $root): array
{
    $methods = [];
    foreach (directPhpFiles($root . '/' . FACADE_EXTENSION_DIRECTORY) as $file) {
        $class = basename($file, '.php');
        $code = file_get_contents($file);
        if (false === $code) {
            continue;
        }

        $tokens = significantTokens(token_get_all($code));
        $count = count($tokens);
        for ($i = 0; $i < $count; ++$i) {
            $token = $tokens[$i];
            if (!is_array($token) || T_FUNCTION !== $token[0]) {
                continue;
            }
            $next = $tokens[$i + 1] ?? null;
            if (is_array($next) && T_STRING === $next[0]) {
                $methods[$class . '::' . $next[1]] = true;
            }
        }
    }

    return $methods;
}

/**
 * 给出旧调用的迁移建议。
 *
 * 顺序：人工措辞表（更贴项目语境）→ 对等矩阵目标 → 项目扩展方法 → 按类名兜底。
 *
 * @param string $method 形如 Base::config
 * @param array<string, true> $extensionMethods 项目扩展方法集合
 * @return string 建议
 */
function suggestFor(string $method, array $extensionMethods = []): string
{
    $map = [
        'Base::config' => 'Kernel::config()->get()',
        'Base::env' => 'ServerContext / Environment',
        'Base::json' => 'Param\\Json::encode() / decode()',
        'Base::lang' => 'Kernel::trans()',
        'Base::oracle' => '项目工厂或容器',
        'Client::ip' => 'ClientInfo::ip()',
        'Data::fieldJson' => 'Data\\Field::json()',
        'Data::paramEmpty' => 'Data\\Param::missing()',
        'Data::whereMake' => 'Sql\\WhereBuilder',
        'File::getList' => 'Filesystem::listFiles()',
        'Math::rand' => 'Random::string()',
        'Param::define' => 'Shape / Pipeline',
        'Param::json' => 'Param\\Json',
        'Param::merge' => 'Merger::merge()',
        'Server::branch' => '项目中间件 + RequestContext',
        'Server::echo' => 'Envelope',
        'Server::format' => 'Envelope::toArray() 或项目序列化',
        'Service::http' => 'HttpClient\\Client',
        'Text::throwable' => '抛异常 + 统一映射',
        'Text::timeMilli' => 'Time::milli()',
        'View::echo' => 'Responder / Renderer',
        'View::log' => 'PSR-3 LoggerInterface',
    ];
    if (isset($map[$method])) {
        return $map[$method];
    }
    $target = matrixTarget($method);
    if (null !== $target) {
        return $target;
    }
    if (isset($extensionMethods[$method])) {
        return '项目扩展方法，随 facade_ext 迁移';
    }
    $class = explode('::', $method, 2)[0];

    return in_array($class, FRAMEWORK_CLASSES, true) ? '见 2.0 API 对照' : '项目自有方法，保留实现';
}

/**
 * 从对等矩阵取 v2 目标。
 *
 * 只在首次调用时读取矩阵并缓存；文件缺失或结构不符时返回 null，工具仍可运行。
 *
 * @param string $method 形如 Text::explode
 * @return string|null 目标描述
 */
function matrixTarget(string $method): ?string
{
    static $matrix = null;
    if (null === $matrix) {
        $file = dirname(__DIR__) . '/' . PARITY_MATRIX;
        $raw = is_file($file) ? file_get_contents($file) : false;
        $decoded = false === $raw ? null : json_decode($raw, true);
        $matrix = is_array($decoded) ? $decoded : [];
    }

    $parts = explode('::', $method, 2);
    if (2 !== count($parts)) {
        return null;
    }
    $facades = $matrix['facades'] ?? null;
    if (!is_array($facades)) {
        return null;
    }
    $facade = $facades[$parts[0]] ?? null;
    if (!is_array($facade)) {
        return null;
    }
    $entry = $facade[$parts[1]] ?? null;
    if (!is_array($entry)) {
        return null;
    }
    $target = $entry['target'] ?? null;

    return is_string($target) && '' !== $target ? $target : null;
}

/**
 * 输出 JSON。
 *
 * @param array<string, mixed> $data 数据
 * @return void
 */
function emitJson(array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo (false === $json ? '{}' : $json) . PHP_EOL;
}

/**
 * 生成画像 Markdown。
 *
 * @param array<string, mixed> $profile 画像
 * @return string Markdown
 */
function formatProfileMarkdown(array $profile): string
{
    $extension = is_array($profile['extension']) ? $profile['extension'] : [];
    $usage = is_array($profile['usage']) ? $profile['usage'] : [];
    $lines = [
        '# 升级体检报告',
        '',
        '- 项目根：`' . $profile['root'] . '`',
        '- PHP 约束：`' . ('' === $profile['php']['constraint'] ? '未声明' : $profile['php']['constraint']) . '`',
        '- 宿主框架：' . $profile['framework'],
        '- 入口：' . ([] === $profile['entry'] ? '未识别' : '`' . implode('`、`', $profile['entry']) . '`'),
        '- Composer：json=' . ($profile['composer']['json'] ? '是' : '否')
            . '，lock=' . ($profile['composer']['lock'] ? '是' : '否')
            . '，autoload=' . ($profile['composer']['autoload'] ? '是' : '否'),
        '- 扩展模块：' . ([] === $profile['modules'] ? '无' : implode('、', $profile['modules'])),
        '- 体量档位：' . $usage['tier'] . '（`\\fxapp\\*::` ' . $usage['fxapp'] . ' 处）',
        '',
        '## 阻塞项',
        '',
    ];
    if ([] === $profile['blockers']) {
        $lines[] = '- 无';
    } else {
        foreach ($profile['blockers'] as $blocker) {
            $lines[] = '- ' . $blocker;
        }
    }
    if ([] !== $profile['notes']) {
        $lines[] = '';
        $lines[] = '## 提示';
        $lines[] = '';
        foreach ($profile['notes'] as $note) {
            $lines[] = '- ' . $note;
        }
    }
    $lines[] = '';
    $lines[] = '## 扩展目录';
    $lines[] = '';
    $lines[] = '| 项 | 数量 / 状态 |';
    $lines[] = '| --- | --- |';
    $lines[] = '| 配置文件 | ' . $extension['config'] . ' |';
    $lines[] = '| 控制器 | ' . $extension['controller'] . ' |';
    $lines[] = '| facade_ext | ' . $extension['facade_ext'] . ' |';
    $lines[] = '| oracle | ' . $extension['oracle'] . ' |';
    $lines[] = '| 语言文件 | ' . $extension['language'] . ' |';
    $lines[] = '| store 钩子 | ' . ([] === $extension['store_hooks'] ? '未发现' : implode('、', $extension['store_hooks'])) . ' |';
    $lines[] = '| 继承 \\fxyin\\Facade | ' . $extension['facade_extends'] . ' |';
    $lines[] = '| think/Exception | ' . ($extension['exception_handler'] ? '存在' : '不存在') . ' |';
    $lines[] = '';
    $lines[] = '## 建议路线';
    $lines[] = '';
    $lines[] = '- ' . $profile['recommendedRoute'] . '：详见升级总览的路线选择。';
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

/**
 * 生成扫描 Markdown。
 *
 * @param array<string, mixed> $scan 统计
 * @return string Markdown
 */
function formatScanMarkdown(array $scan): string
{
    $counts = is_array($scan['counts']) ? $scan['counts'] : [];
    $lines = [
        '# 旧 API 扫描',
        '',
        '- 项目根：`' . $scan['root'] . '`',
        '- 扫描文件：' . $scan['files'] . ' 个',
        '',
        '## 统计',
        '',
        '| 项 | 数量 |',
        '| --- | --- |',
        '| `\\fxapp\\*::` | ' . $counts['fxapp'] . ' |',
        '| `$echo[n]` | ' . $counts['echo_indexes'] . ' |',
        '| 点分模式号 | ' . $counts['dot_codes'] . ' |',
        '| `is_*` | ' . $counts['is_functions'] . ' |',
        '| `fxy_*` | ' . $counts['fxy_functions'] . ' |',
        '| `\\fxyin\\` | ' . $counts['fxyin_refs'] . ' |',
        '| 继承 `\\fxyin\\Facade` | ' . $counts['facade_extends'] . ' |',
        '| `facade_ext` 引用 | ' . $counts['facade_ext_refs'] . ' |',
        '| `oracle` 引用 | ' . $counts['oracle_refs'] . ' |',
        '',
        '## 目录分布',
        '',
        '| 目录 | `\\fxapp\\*::` |',
        '| --- | --- |',
    ];
    foreach ($scan['byDirectory'] as $directory => $count) {
        $lines[] = '| ' . $directory . ' | ' . $count . ' |';
    }
    $lines[] = '';
    $lines[] = '## 高频调用';
    $lines[] = '';
    $lines[] = '| 调用 | 次数 | 建议 |';
    $lines[] = '| --- | --- | --- |';
    foreach ($scan['topMethods'] as $item) {
        $lines[] = '| `' . $item['method'] . '` | ' . $item['count'] . ' | ' . $item['suggestion'] . ' |';
    }
    $lines[] = '';

    return implode(PHP_EOL, $lines);
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
