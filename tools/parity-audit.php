<?php

declare(strict_types=1);

/**
 * 对等矩阵审计。
 *
 * 校验 v1.6.1 能力快照结构完整、计数正确、每一项都有 v2 目标与状态。
 *
 * 用法：php fxyin parity
 *
 * 实现层：由根入口 fxyin 的 parity 域调用，也可直接执行本文件；本仓专用，不随包分发。
 */

/** 允许的状态 */
const PARITY_STATUSES = ['equiv', 'add', 'covered', 'removed'];

/** 预期计数 */
const EXPECTED = [
    'facade_methods' => 92,
    'globals' => 18,
    'runtime_methods' => 27,
    'language_resources' => 6,
    'config_resources' => 4,
];

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    echo <<<'TEXT'
        php fxyin parity

        校验 1.6.1 能力快照结构完整、计数正确、每一项都有 v2 目标与状态。

        退出码：0 通过 / 1 审计失败

        TEXT;

    exit(0);
}

$root = dirname(__DIR__);
$file = $root . '/resources/parity/legacy-api.json';
$raw = is_file($file) ? file_get_contents($file) : false;
if (false === $raw) {
    fwrite(STDERR, '无法读取对等快照：' . $file . PHP_EOL);

    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, '对等快照不是合法 JSON' . PHP_EOL);

    exit(1);
}

$issues = [];
$facadeMethods = 0;
$globals = 0;
$runtimeMethods = 0;

$checkEntries = static function (array $entries, string $group) use (&$issues): int {
    $count = 0;
    foreach ($entries as $name => $entry) {
        $count += 1;
        if (!is_array($entry)) {
            $issues[] = $group . '.' . $name . ' 缺少目标定义';

            continue;
        }
        $target = $entry['target'] ?? '';
        $status = $entry['status'] ?? '';
        if (!is_string($target) || '' === trim($target)) {
            $issues[] = $group . '.' . $name . ' 缺少 target';
        }
        if (!is_string($status) || !in_array($status, PARITY_STATUSES, true)) {
            $issues[] = $group . '.' . $name . ' 状态非法：' . (string) $status;
        }
    }

    return $count;
};

$facades = $data['facades'] ?? null;
if (!is_array($facades)) {
    $issues[] = '缺少 facades';
} else {
    foreach ($facades as $facade => $entries) {
        if (!is_array($entries)) {
            $issues[] = 'facade ' . $facade . ' 不是对象';

            continue;
        }
        $facadeMethods += $checkEntries($entries, 'facade.' . $facade);
    }
}

$globalEntries = $data['globals'] ?? null;
if (!is_array($globalEntries)) {
    $issues[] = '缺少 globals';
} else {
    $globals = $checkEntries($globalEntries, 'globals');
}

$runtime = $data['runtime'] ?? null;
if (!is_array($runtime)) {
    $issues[] = '缺少 runtime';
} else {
    foreach ($runtime as $class => $entries) {
        if (!is_array($entries)) {
            $issues[] = 'runtime ' . $class . ' 不是对象';

            continue;
        }
        $runtimeMethods += $checkEntries($entries, 'runtime.' . $class);
    }
}

$resources = $data['resources'] ?? null;
$languageResources = is_array($resources['language'] ?? null) ? count($resources['language']) : 0;
$configResources = is_array($resources['config'] ?? null) ? count($resources['config']) : 0;

$actual = [
    'facade_methods' => $facadeMethods,
    'globals' => $globals,
    'runtime_methods' => $runtimeMethods,
    'language_resources' => $languageResources,
    'config_resources' => $configResources,
];

foreach (EXPECTED as $key => $expected) {
    if ($actual[$key] !== $expected) {
        $issues[] = sprintf('%s 计数不符：期望 %d，实际 %d', $key, $expected, $actual[$key]);
    }
}

$docs = $root . '/docs/parity-1.6.1.md';
if (!is_file($docs)) {
    $issues[] = '缺少对等矩阵文档 docs/parity-1.6.1.md';
}

if ([] !== $issues) {
    fwrite(STDERR, '对等矩阵审计失败：' . PHP_EOL);
    foreach ($issues as $issue) {
        fwrite(STDERR, '  - ' . $issue . PHP_EOL);
    }

    exit(1);
}

echo '对等矩阵审计通过：门面 92、全局 18、运行时 27、语言包 6、默认配置 4' . PHP_EOL;

exit(0);
