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

/** 1.x 框架默认配置：门面命名空间与基础加载目录写在 env.facade / loader.fxapp */
const BASE_CONFIG = 'extend/fxri/base/config.php';

/** 1.x 框架根目录 */
const FRAMEWORK_ROOT = 'extend/fxri';

/** 扩展应用目录：项目扩展、门面扩展与 oracle 都放在这里 */
const DATA_DIRECTORY = 'extend/fxri/data';

/** 应用目录配置缺失或无法解析时的兜底候选 */
const FALLBACK_APP_DIRECTORIES = ['app', 'application'];

/** 对等矩阵：升级建议的第二来源，避免人工表与矩阵漂移 */
const PARITY_MATRIX = 'tests/Parity/legacy-api.json';

/** 配置解析失败时的兜底门面类，取自 1.6.1 的 facade 目录 */
const FALLBACK_FACADE_CLASSES = ['Base', 'Client', 'Data', 'File', 'Math', 'Param', 'Safe', 'Server', 'Service', 'Text', 'View'];

/** 配置解析失败时的兜底全局函数，取自 1.6.1 的 function 目录 */
const FALLBACK_GLOBALS = ['is_blank', 'is_json', 'is_time', 'is_function', 'is_mobile', 'is_email', 'is_zipcode', 'is_ipv4', 'is_ipv6', 'is_url', 'is_ip', 'fxy_lang', 'fxy_config', 'fxy_cookie', 'fxy_env', 'fxy_dump', 'fxy_json', 'fxy_echo'];

/** 入口候选：不同宿主框架的入口位置 */
const ENTRY_CANDIDATES = ['index.php', 'public/index.php', 'public/router.php', 'extend/fxri/bootstrap.php', 'core/start.php'];

/** 人工措辞表：比矩阵目标更贴项目语境的表述，键与 PHP 一致按不区分大小写匹配 */
const SUGGESTION_MAP = [
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

/** 目录名排除：第三方依赖与运行产物 */
const EXCLUDED_DIRECTORIES = ['vendor', 'runtime', 'node_modules', '.git'];

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
    $layout = projectLayout($root);
    $scan = scanProject($root);
    $modules = listSubdirectories($root . '/' . FRAMEWORK_ROOT);
    $entry = [];
    foreach (ENTRY_CANDIDATES as $candidate) {
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
        'extension' => inspectExtension($root, $layout),
        'usage' => [
            'fxapp' => $scan['counts']['fxapp'],
            'tier' => $tier,
        ],
        'layout' => [
            'app' => $layout['app'],
            'namespace' => $layout['namespace'],
            'extensionNamespace' => $layout['extensionNamespace'],
            'extensionDirectory' => $layout['extensionDirectory'],
            'facadeClasses' => count($layout['facadeClasses']),
            'globals' => count($layout['globals']),
            'entries' => $layout['entries'],
            'configured' => $layout['configured'],
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
    $layout = projectLayout($root);
    $namespace = (string) $layout['namespace'];
    $extensionNamespace = is_string($layout['extensionNamespace']) ? $layout['extensionNamespace'] : $namespace . '\\facade_ext';
    $methodPattern = '/\\\\' . nsPattern($namespace) . '\\\\([A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)/';
    $callPattern = '/\\\\' . nsPattern($namespace) . '\\\\([A-Za-z_][A-Za-z0-9_]*)::/';
    $globals = $layout['globals'];
    $isGlobals = array_values(array_filter($globals, static fn (string $name): bool => str_starts_with($name, 'is_')));
    if ([] === $isGlobals) {
        $isGlobals = array_values(array_filter(FALLBACK_GLOBALS, static fn (string $name): bool => str_starts_with($name, 'is_')));
    }
    $globalsPattern = '/\b(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '/'), $isGlobals)) . ')\s*\(/';

    $directories = scanDirectories($root);
    $files = collectPhpFiles($root, $directories, $layout['excludedPaths']);
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

        $counts['fxapp'] += countPattern($methodPattern, $code);
        $counts['echo_indexes'] += countPattern('/\$echo\[\d\]/', $code);
        $counts['dot_codes'] += countPattern('/([\'"])[12]\.\d(?:\.\d)?\1/', $code);
        $counts['is_functions'] += countPattern($globalsPattern, $code);
        $counts['fxy_functions'] += countPattern('/\bfxy_[a-z_]+\s*\(/', $code);
        $counts['fxyin_refs'] += countPattern('/\\\\fxyin\\\\/', $code);
        $counts['facade_extends'] += countPattern('/extends\s+\\\\fxyin\\\\Facade/', $code);
        $counts['facade_ext_refs'] += countPattern('/\\\\' . nsPattern($extensionNamespace) . '\\\\/', $code);
        $counts['oracle_refs'] += countPattern('/\\\\' . nsPattern($namespace) . '\\\\oracle\\\\/', $code);

        $relative = relativePath($root, $file);
        foreach (array_keys($byDirectory) as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory . '/')) {
                $byDirectory[$directory] += countPattern($callPattern, $code);

                break;
            }
        }

        $matches = [];
        if (preg_match_all($methodPattern, $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $key = $match[1] . '::' . $match[2];
                $methods[$key] = ($methods[$key] ?? 0) + 1;
            }
        }
    }

    arsort($methods);
    $extensionMethods = facadeExtensionMethods($root, $layout);
    $declared = declaredFacadeMethods($root, $layout);
    $localOnly = [];
    foreach ($methods as $method => $count) {
        $key = strtolower($method);
        [$class] = explode('::', $key, 2);
        if (!isset($declared[$key]) || isset($extensionMethods[$key])) {
            continue;
        }
        if (!in_array($class, array_map(static fn (string $name): string => strtolower($name), $layout['facadeClasses']), true)) {
            continue;
        }
        if (null !== matrixTarget($method)) {
            continue;
        }
        $localOnly[$method] = $count;
    }

    $topMethods = [];
    foreach (array_slice($methods, 0, 20, true) as $method => $count) {
        $topMethods[] = [
            'method' => $method,
            'count' => $count,
            'suggestion' => suggestFor($method, $extensionMethods, $layout['facadeClasses']),
        ];
    }

    return [
        'root' => $root,
        'namespace' => $namespace,
        'files' => count($files),
        'counts' => $counts,
        'byDirectory' => $byDirectory,
        'topMethods' => $topMethods,
        'localOnlyMethods' => $localOnly,
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
 * @param array<string, mixed> $layout 项目布局
 * @return array<string, mixed> 扩展目录画像
 */
function inspectExtension(string $root, array $layout): array
{
    $app = $root . '/' . DATA_DIRECTORY . '/app';
    $controller = $app . '/controller';
    if (is_string($layout['extensionDirectory'])) {
        $controller = dirname($root . '/' . $layout['extensionDirectory']);
    }
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
function collectPhpFiles(string $root, array $directories, array $excludedPaths = ['/extend/fxri/base/']): array
{
    $files = [];
    foreach ($directories as $directory) {
        $path = $root . '/' . $directory;
        if (is_file($path)) {
            if ('php' === strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
                $files[] = str_replace('\\', '/', $path);
            }

            continue;
        }
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $file) use ($excludedPaths): bool {
                    $path = str_replace('\\', '/', $file->getPathname());
                    foreach ($excludedPaths as $excluded) {
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
 * 解析项目布局。
 *
 * 1.x 的路径与命名空间都写在两份配置里：`extend/fxri/base/config.php` 给默认值（索引 0），
 * `extend/fxri/data/config.php` 按索引覆盖。这里只做静态解析，不 include 项目文件；
 * 解析失败时回落到目录探测与 1.6.1 的默认清单，并在报告里标注。
 *
 * @param string $root 项目根
 * @return array<string, mixed> 布局
 */
function projectLayout(string $root): array
{
    static $cache = [];
    $key = str_replace('\\', '/', (string) realpath($root));
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $baseFile = $root . '/' . BASE_CONFIG;
    $dataFile = $root . '/' . APP_DIRECTORY_CONFIG;
    $baseTokens = configTokens($baseFile);
    $dataTokens = configTokens($dataFile);

    $app = null;
    if (null !== $dataTokens) {
        $app = resolveConfiguredApp($dataTokens, $root, dirname($dataFile));
    }

    $namespace = 'fxapp';
    $extensionNamespace = null;
    if (null !== $baseTokens) {
        $namespace = resolveNamespace($baseTokens);
    }
    if (null !== $dataTokens) {
        $extensionNamespace = resolveExtensionNamespace($dataTokens);
    }

    $loaderDirectories = [];
    if (null !== $baseTokens) {
        $loaderDirectories = array_merge($loaderDirectories, loaderDirectories($baseTokens, $root, dirname($baseFile)));
    }
    if (null !== $dataTokens) {
        $loaderDirectories = array_merge($loaderDirectories, loaderDirectories($dataTokens, $root, dirname($dataFile)));
    }

    $extensionDirectory = null;
    if (null !== $extensionNamespace) {
        $segment = lastSegment($extensionNamespace);
        foreach ($loaderDirectories as $directory) {
            $candidate = $directory . '/' . $segment;
            if (is_dir($candidate)) {
                $extensionDirectory = relativeTo($root, $candidate);

                break;
            }
        }
    }

    $excludedPaths = ['/extend/fxri/base/'];
    foreach (listSubdirectories($root . '/' . FRAMEWORK_ROOT) as $module) {
        if (!in_array($module, ['base', 'data'], true)) {
            $excludedPaths[] = '/extend/fxri/' . $module . '/';
        }
    }

    $layout = [
        'app' => $app,
        'namespace' => $namespace,
        'extensionNamespace' => $extensionNamespace,
        'extensionDirectory' => $extensionDirectory,
        'facadeDirectory' => facadeDirectory($root, $loaderDirectories),
        'facadeClasses' => facadeClasses($root, $loaderDirectories),
        'globals' => globalFunctions($root),
        'excludedPaths' => $excludedPaths,
        'entries' => entryPaths($root),
        'configured' => null !== $dataTokens && null !== $app,
    ];
    $cache[$key] = $layout;

    return $layout;
}

/**
 * 读取配置文件的精简标记。
 *
 * @param string $file 配置文件
 * @return array<int, array{0: int, 1: string, 2: int}|string>|null 标记
 */
function configTokens(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $code = file_get_contents($file);
    if (false === $code) {
        return null;
    }

    return significantTokens(token_get_all($code));
}

/**
 * 解析配置声明的应用目录。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 配置标记
 * @param string $root 项目根
 * @param string $configDir 配置目录
 * @return string|null 相对项目根的应用目录
 */
function resolveConfiguredApp(array $tokens, string $root, string $configDir): ?string
{
    $env = valueTokens($tokens, 'env');
    $base = null === $env ? null : valueTokens($env, 'base');
    $app = null === $base ? null : valueTokens($base, 'app');
    if (null === $app) {
        return null;
    }

    $path = evaluatePathTokens($app, $root, $configDir);

    return null === $path ? null : relativeTo($root, $path);
}

/**
 * 解析门面根命名空间。
 *
 * 1.x 把基础门面声明为 `env.facade[0]`（形如 `\fxapp\facade\`），去掉末级即根命名空间。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 配置标记
 * @return string 根命名空间
 */
function resolveNamespace(array $tokens): string
{
    foreach (facadeNamespaces($tokens) as $key => $value) {
        if (0 === $key) {
            $namespace = namespaceRoot($value);
            if ('' !== $namespace) {
                return $namespace;
            }
        }
    }

    return 'fxapp';
}

/**
 * 解析项目扩展门面的命名空间。
 *
 * 扩展门面声明在 `env.facade` 的非 0 索引上，形如 `\fxapp\facade_ext\`。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 配置标记
 * @return string|null 扩展命名空间
 */
function resolveExtensionNamespace(array $tokens): ?string
{
    foreach (facadeNamespaces($tokens) as $key => $value) {
        if (0 !== $key && '' !== $value) {
            return trim($value, '\\');
        }
    }

    return null;
}

/**
 * 取配置里声明的门面命名空间。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 配置标记
 * @return array<int|string, string> 索引 => 命名空间
 */
function facadeNamespaces(array $tokens): array
{
    $env = valueTokens($tokens, 'env');
    $facade = null === $env ? null : valueTokens($env, 'facade');
    if (null === $facade) {
        return [];
    }

    $namespaces = [];
    foreach (arrayPairs($facade) as $key => $value) {
        foreach ($value as $token) {
            if (is_array($token) && T_CONSTANT_ENCAPSED_STRING === $token[0]) {
                $namespaces[$key] = trim(literalValue($token[1]), '\\');

                break;
            }
        }
    }

    return $namespaces;
}

/**
 * 取配置里声明的门面与扩展加载目录。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 配置标记
 * @param string $root 项目根
 * @param string $configDir 配置目录
 * @return list<string> 绝对目录
 */
function loaderDirectories(array $tokens, string $root, string $configDir): array
{
    $loader = valueTokens($tokens, 'loader');
    $fxapp = null === $loader ? null : valueTokens($loader, 'fxapp');
    if (null === $fxapp) {
        return [];
    }

    $directories = [];
    foreach (arrayPairs($fxapp) as $value) {
        $path = evaluatePathTokens($value, $root, $configDir);
        if (null !== $path && is_dir($path)) {
            $directories[] = rtrim(str_replace('\\', '/', $path), '/');
        }
    }

    return $directories;
}

/**
 * 取框架副本里的门面目录。
 *
 * @param string $root 项目根
 * @param list<string> $loaderDirectories 加载目录
 * @return string|null 相对项目根的门面目录
 */
function facadeDirectory(string $root, array $loaderDirectories): ?string
{
    $candidates = [];
    foreach ($loaderDirectories as $directory) {
        $candidates[] = $directory . '/facade';
    }
    $candidates[] = $root . '/' . FRAMEWORK_ROOT . '/base/app/controller/facade';

    foreach ($candidates as $directory) {
        if (is_dir($directory) && [] !== directPhpFiles($directory)) {
            return relativeTo($root, $directory);
        }
    }

    return null;
}

/**
 * 列出门面类名。
 *
 * @param string $root 项目根
 * @param list<string> $loaderDirectories 加载目录
 * @return list<string> 门面类名
 */
function facadeClasses(string $root, array $loaderDirectories): array
{
    $candidates = [];
    foreach ($loaderDirectories as $directory) {
        $candidates[] = $directory . '/facade';
    }
    $candidates[] = $root . '/' . FRAMEWORK_ROOT . '/base/app/controller/facade';

    foreach ($candidates as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        $classes = [];
        foreach (directPhpFiles($directory) as $file) {
            $classes[] = basename($file, '.php');
        }
        sort($classes);
        if ([] !== $classes) {
            return $classes;
        }
    }

    return FALLBACK_FACADE_CLASSES;
}

/**
 * 列出 1.x 全局函数。
 *
 * @param string $root 项目根
 * @return list<string> 函数名
 */
function globalFunctions(string $root): array
{
    $files = glob($root . '/' . FRAMEWORK_ROOT . '/*/app/function/*.php') ?: [];
    $functions = [];
    foreach ($files as $file) {
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
            if (!is_array($next) || T_STRING !== $next[0]) {
                continue;
            }
            if (str_starts_with($next[1], 'is_') || str_starts_with($next[1], 'fxy_')) {
                $functions[] = $next[1];
            }
        }
    }
    $functions = array_values(array_unique($functions));
    sort($functions);

    return [] === $functions ? FALLBACK_GLOBALS : $functions;
}

/**
 * 确定入口路径。
 *
 * ThinkPHP 系入口在 `public`，非 TP 项目入口在根目录；按存在性选择，不写死。
 *
 * @param string $root 项目根
 * @return list<string> 相对路径
 */
function entryPaths(string $root): array
{
    if (is_dir($root . '/public')) {
        return ['public'];
    }

    $entries = [];
    foreach (ENTRY_CANDIDATES as $candidate) {
        if (!str_contains($candidate, '/') && is_file($root . '/' . $candidate)) {
            $entries[] = $candidate;
        }
    }

    return $entries;
}

/**
 * 把数组内容按顶层逗号拆成 键 => 值标记。
 *
 * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens 数组内容标记
 * @return array<int|string, array<int, array{0: int, 1: string, 2: int}|string>> 键 => 值标记
 */
function arrayPairs(array $tokens): array
{
    $segments = [];
    $current = [];
    $depth = 0;
    foreach ($tokens as $token) {
        $text = is_array($token) ? $token[1] : $token;
        if (',' === $text && 0 === $depth) {
            $segments[] = $current;
            $current = [];

            continue;
        }
        if ('[' === $text || '(' === $text) {
            ++$depth;
        } elseif (']' === $text || ')' === $text) {
            --$depth;
        }
        $current[] = $token;
    }
    if ([] !== $current) {
        $segments[] = $current;
    }

    $pairs = [];
    foreach ($segments as $segment) {
        $arrow = null;
        foreach ($segment as $index => $token) {
            if (is_array($token) && T_DOUBLE_ARROW === $token[0]) {
                $arrow = $index;

                break;
            }
        }
        if (null === $arrow || 0 === $arrow) {
            continue;
        }
        $keyToken = $segment[$arrow - 1];
        $key = is_array($keyToken) && T_CONSTANT_ENCAPSED_STRING === $keyToken[0]
            ? literalValue($keyToken[1])
            : (is_array($keyToken) ? $keyToken[1] : $keyToken);
        $pairs[$key] = array_slice($segment, $arrow + 1);
    }

    return $pairs;
}

/**
 * 取路径最后一段。
 *
 * @param string $path 路径或命名空间
 * @return string 最后一段
 */
function lastSegment(string $path): string
{
    $value = trim($path, '\\/');
    $position = strrpos($value, '\\');
    if (false !== $position) {
        return substr($value, $position + 1);
    }
    $position = strrpos($value, '/');

    return false === $position ? $value : substr($value, $position + 1);
}

/**
 * 把命名空间转成正则里的字面片段。
 *
 * @param string $namespace 命名空间
 * @return string 正则片段
 */
function nsPattern(string $namespace): string
{
    return str_replace('\\', '\\\\', $namespace);
}

/**
 * 去掉命名空间最后一段。
 *
 * @param string $namespace 命名空间
 * @return string 根命名空间
 */
function namespaceRoot(string $namespace): string
{
    $value = trim($namespace, '\\');
    $position = strrpos($value, '\\');

    return false === $position ? $value : substr($value, 0, $position);
}

/**
 * 解析项目声明的应用目录。
 *
 * 1.x 在 `extend/fxri/data/config.php` 的 `env.base.app` 里声明应用目录，
 * 取值可能是 `application`、`app`、`apps` 或其它名称，因此不按目录名硬编码。
 *
 * @param string $root 项目根
 * @return string|null 相对项目根的应用目录
 */
function resolveAppDirectory(string $root): ?string
{
    $layout = projectLayout($root);

    return is_string($layout['app']) ? $layout['app'] : null;
}

/**
 * 确定扫描目录。
 *
 * 应用目录取自项目配置；配置缺失或无法静态解析时才按存在性兜底探测常见命名。
 *
 * @param string $root 项目根
 * @return list<string> 相对项目根的目录或文件列表
 */
function scanDirectories(string $root): array
{
    $layout = projectLayout($root);
    $directories = [];
    $app = is_string($layout['app']) ? $layout['app'] : null;
    if (null !== $app && is_dir($root . '/' . $app)) {
        $directories[] = $app;
    } else {
        foreach (FALLBACK_APP_DIRECTORIES as $candidate) {
            if (is_dir($root . '/' . $candidate)) {
                $directories[] = $candidate;
            }
        }
    }
    $directories[] = DATA_DIRECTORY;
    foreach ($layout['entries'] as $entry) {
        $directories[] = $entry;
    }

    return array_values(array_unique($directories));
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
 * @param array<string, mixed> $layout 项目布局
 * @return array<string, true> 形如 `safe::rsapriext` 的小写集合
 */
function facadeExtensionMethods(string $root, array $layout): array
{
    $methods = [];
    $directory = is_string($layout['extensionDirectory']) ? $layout['extensionDirectory'] : null;
    if (null === $directory || !is_dir($root . '/' . $directory)) {
        return $methods;
    }
    foreach (directPhpFiles($root . '/' . $directory) as $file) {
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
                $methods[strtolower($class . '::' . $next[1])] = true;
            }
        }
    }

    return $methods;
}

/**
 * 收集本地框架副本声明的门面方法。
 *
 * 用于识别「项目副本里额外加过、正式标签没有」的方法：这类方法随副本一起消失，
 * 2.0 没有等价 API，需要在报告里单独提示。
 *
 * @param string $root 项目根
 * @param array<string, mixed> $layout 项目布局
 * @return array<string, true> 形如 `base::config` 的小写集合
 */
function declaredFacadeMethods(string $root, array $layout): array
{
    $methods = [];
    $directory = is_string($layout['facadeDirectory']) ? $layout['facadeDirectory'] : null;
    if (null === $directory || !is_dir($root . '/' . $directory)) {
        return $methods;
    }

    foreach (directPhpFiles($root . '/' . $directory) as $file) {
        $class = basename($file, '.php');
        $code = (string) file_get_contents($file);
        if (!preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $code, $matches)) {
            continue;
        }
        foreach ($matches[1] as $method) {
            if (str_starts_with($method, '__')) {
                continue;
            }
            $methods[strtolower($class . '::' . $method)] = true;
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
 * @param list<string> $facadeClasses 门面类名
 * @return string 建议
 */
function suggestFor(string $method, array $extensionMethods = [], array $facadeClasses = []): string
{
    static $map = null;
    if (null === $map) {
        $map = array_change_key_case(SUGGESTION_MAP, CASE_LOWER);
    }

    $normalized = strtolower($method);
    if (isset($map[$normalized])) {
        return $map[$normalized];
    }
    $target = matrixTarget($method);
    if (null !== $target) {
        return $target;
    }
    if (isset($extensionMethods[$normalized])) {
        return '项目扩展方法，随 facade_ext 迁移';
    }
    $class = explode('::', $method, 2)[0];
    $classes = [] === $facadeClasses ? FALLBACK_FACADE_CLASSES : $facadeClasses;

    return in_array($class, $classes, true) ? '项目扩展或服务方法，随扩展目录迁移' : '项目自有方法，保留实现';
}

/**
 * 从对等矩阵取 v2 目标。
 *
 * 只在首次调用时把矩阵摊成小写索引并缓存；PHP 方法名不区分大小写，`Base::Config`
 * 与 `Base::config` 必须命中同一目标。文件缺失或结构不符时返回 null，工具仍可运行。
 *
 * @param string $method 形如 Text::explode
 * @return string|null 目标描述
 */
function matrixTarget(string $method): ?string
{
    static $index = null;
    if (null === $index) {
        $index = matrixTargetIndex();
    }

    return $index[strtolower($method)] ?? null;
}

/**
 * 构建矩阵目标索引。
 *
 * @return array<string, string> 形如 text::explode => Text\Str::explode
 */
function matrixTargetIndex(): array
{
    $file = dirname(__DIR__) . '/' . PARITY_MATRIX;
    $raw = is_file($file) ? file_get_contents($file) : false;
    $matrix = false === $raw ? null : json_decode($raw, true);
    if (!is_array($matrix)) {
        return [];
    }
    $facades = $matrix['facades'] ?? null;
    if (!is_array($facades)) {
        return [];
    }

    $index = [];
    foreach ($facades as $facade => $methods) {
        if (!is_array($methods)) {
            continue;
        }
        foreach ($methods as $name => $entry) {
            $target = is_array($entry) ? ($entry['target'] ?? null) : null;
            if (is_string($target) && '' !== $target) {
                $index[strtolower($facade . '::' . $name)] = $target;
            }
        }
    }

    return $index;
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
    $layout = is_array($profile['layout']) ? $profile['layout'] : [];
    $namespace = '\\' . (string) ($layout['namespace'] ?? 'fxapp') . '\\';
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
        '- 应用目录：' . (is_string($layout['app'] ?? null) ? '`' . $layout['app'] . '`' : '未解析（按存在性兜底）')
            . '，门面命名空间：`' . $namespace . '`',
        '- 扩展目录：' . (is_string($layout['extensionDirectory'] ?? null) ? '`' . $layout['extensionDirectory'] . '`' : '未解析')
            . '，门面类 ' . ($layout['facadeClasses'] ?? 0) . ' 个、全局函数 ' . ($layout['globals'] ?? 0) . ' 个',
        '- 体量档位：' . $usage['tier'] . '（`' . $namespace . '*::` ' . $usage['fxapp'] . ' 处）',
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
    $namespace = '\\' . (string) ($scan['namespace'] ?? 'fxapp') . '\\';
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
        '| `' . $namespace . '*::` | ' . $counts['fxapp'] . ' |',
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
        '| 目录 | `' . $namespace . '*::` |',
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

    $localOnly = is_array($scan['localOnlyMethods'] ?? null) ? $scan['localOnlyMethods'] : [];
    if ([] !== $localOnly) {
        $lines[] = '## 本地新增方法';
        $lines[] = '';
        $lines[] = '本地框架副本声明、但对等矩阵没有目标的方法；这些方法随副本删除而消失，需先定落点。';
        $lines[] = '';
        $lines[] = '| 方法 | 次数 |';
        $lines[] = '| --- | --- |';
        foreach ($localOnly as $method => $count) {
            $lines[] = '| `' . $method . '` | ' . $count . ' |';
        }
        $lines[] = '';
    }

    return implode(PHP_EOL, $lines);
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    exit(main($argv));
}
