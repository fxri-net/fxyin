<?php

declare(strict_types=1);

/**
 * 发布包卫生检查。
 *
 * 确认开发期目录不会进入 Composer 分发包，并确认 2.x 根目录不再残留 1.x 的 app/。
 *
 * 用法：php tools/check-package.php
 */

/** 必须从分发包排除的仓库路径 */
const EXPORT_IGNORED_PATHS = [
    '/.github',
    '/tests',
    '/tools',
    '/.tasks',
    '/.trae',
    '/.agents',
    '/AGENTS.md',
    '/.changeset',
    '/docs',
    '/scripts',
    '/package.json',
    '/pnpm-lock.yaml',
    '/phpstan-deprecation.neon',
];

/** 必须固定的行尾规则：平台自动转换会破坏资源字节，导致本地与 CI 校验结果不一致 */
const LINE_ENDING_RULES = [
    '* text=auto eol=lf',
    '/resources/language/** text eol=lf',
    '/resources/config/** text eol=lf',
];

/** 必须随分发包提供的运行资源 */
const RESOURCE_PATHS = [
    '/resources/language',
    '/resources/config',
];

/** 内置语言包 */
const LANGUAGE_FILES = [
    '/resources/language/zh-cn/base.php',
    '/resources/language/zh-cn/env.php',
    '/resources/language/zh-cn/phrase.php',
    '/resources/language/en-us/base.php',
    '/resources/language/en-us/env.php',
    '/resources/language/en-us/phrase.php',
];

/** 内置默认配置 */
const CONFIG_FILES = [
    '/resources/config/app.php',
    '/resources/config/branch.php',
    '/resources/config/env.php',
];

$root = dirname(__DIR__);
$attributesFile = $root . '/.gitattributes';
$attributes = is_file($attributesFile) ? file_get_contents($attributesFile) : false;
if (false === $attributes) {
    fwrite(STDERR, '无法读取 .gitattributes' . PHP_EOL);

    exit(1);
}

$missing = [];
foreach (EXPORT_IGNORED_PATHS as $path) {
    if (1 !== preg_match('/^' . preg_quote($path, '/') . '\s+export-ignore$/m', $attributes)) {
        $missing[] = $path;
    }
}

// 行尾规则是资源字节一致性的根因修复，被误删时门禁必须自己报出来
$missingEol = [];
foreach (LINE_ENDING_RULES as $rule) {
    if (1 !== preg_match('/^' . preg_quote($rule, '/') . '$/m', $attributes)) {
        $missingEol[] = $rule;
    }
}

// 2.x 不再包含 1.x 的 app/ 目录，残留语言包会随分发包泄漏
$stale = glob($root . '/app/language/*/*.php') ?: [];

// 内置资源必须随包分发，且不能被导出排除
$missingResources = [];
foreach (RESOURCE_PATHS as $path) {
    if (!is_dir($root . $path)) {
        $missingResources[] = '资源目录缺失：' . $path;
    }
    if (1 === preg_match('/^' . preg_quote($path, '/') . '\s+export-ignore$/m', $attributes)) {
        $missingResources[] = '资源目录被导出排除：' . $path;
    }
}
foreach (LANGUAGE_FILES as $file) {
    if (!is_file($root . $file)) {
        $missingResources[] = '内置语言包缺失：' . $file;
    }
}
foreach (CONFIG_FILES as $file) {
    if (!is_file($root . $file)) {
        $missingResources[] = '内置默认配置缺失：' . $file;
    }
}

if ([] !== $missing || [] !== $stale || [] !== $missingResources || [] !== $missingEol) {
    fwrite(STDERR, '发布包卫生检查失败：' . PHP_EOL);
    foreach ($missing as $path) {
        fwrite(STDERR, '  - 缺少导出排除：' . $path . PHP_EOL);
    }
    foreach ($missingEol as $rule) {
        fwrite(STDERR, '  - 缺少行尾规则：' . $rule . PHP_EOL);
    }
    foreach ($stale as $file) {
        fwrite(STDERR, '  - 残留 1.x 文件：app/language/' . basename(dirname($file)) . '/' . basename($file) . PHP_EOL);
    }
    foreach ($missingResources as $issue) {
        fwrite(STDERR, '  - ' . $issue . PHP_EOL);
    }

    exit(1);
}

echo '发布包卫生检查通过' . PHP_EOL;

exit(0);
