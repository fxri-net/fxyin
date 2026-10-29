<?php

declare(strict_types=1);

namespace Fxri\Tests\Tools;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * 升级体检工具的端到端测试。
 *
 * 测试在临时目录生成最小项目树，通过子进程调用工具，避免引入对目标项目的执行依赖。
 */
final class UpgradeInspectTest extends TestCase
{
    /** @var list<string> 待清理的临时目录 */
    private array $temporaryDirectories = [];

    /**
     * 清理临时目录。
     */
    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            $this->removeDirectory($directory);
        }
        $this->temporaryDirectories = [];
    }

    /**
     * ThinkPHP 6 项目应识别出框架、扩展目录与调用量。
     */
    public function testProfileOfThinkPhpProject(): void
    {
        $root = $this->makeProject('tp6');
        $this->write($root . '/composer.json', json_encode([
            'require' => [
                'php' => '>=8.2',
                'topthink/framework' => '^6.0',
            ],
        ], JSON_THROW_ON_ERROR));
        $this->write($root . '/composer.lock', json_encode([
            'packages' => [
                ['name' => 'topthink/framework', 'version' => 'v6.0.12'],
            ],
        ], JSON_THROW_ON_ERROR));
        $this->write($root . '/vendor/autoload.php', '');
        $this->write($root . '/think', '');
        $this->write($root . '/public/index.php', '<?php \fxapp\Server::echo(); $echo[2];');
        $this->write($root . '/extend/fxri/base/autoload.php', '<?php \fxapp\Base::config("base");');
        $this->write($root . '/extend/fxri/data/config.php', '<?php return [];');
        $this->write($root . '/extend/fxri/data/app/config/app.php', '<?php return [];');
        $this->write($root . '/extend/fxri/data/app/controller/Base.php', '<?php class Base extends \fxyin\Facade { public function x() { return \fxapp\Base::oracle("base"); } }');
        $this->write($root . '/extend/fxri/data/app/controller/facade_ext/Base.php', '<?php class Base {}');
        $this->write($root . '/extend/fxri/data/app/controller/oracle/Base.php', '<?php class Base {}');
        $this->write($root . '/extend/fxri/data/app/controller/think/Exception.php', '<?php class Exception {}');
        $this->write($root . '/extend/fxri/data/app/store/common.php', '<?php \fxyin\Env::init(); \fxapp\Server::branch();');
        $this->write($root . '/extend/fxri/data/app/language/zh-cn/app.php', '<?php return [];');
        $this->write($root . '/app/controller/Index.php', '<?php \fxapp\Base::config("app.name"); \fxapp\Param::define([], "1.2.3"); is_blank($x); fxy_json($x, "encode"); $echo[2];');

        [$code, $output] = $this->runTool(['profile', '--root=' . $root, '--json']);
        self::assertSame(0, $code, $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('ThinkPHP v6.0.12', $data['framework']);
        self::assertSame('S', $data['usage']['tier']);
        self::assertSame(5, $data['usage']['fxapp']);
        self::assertSame(1, $data['extension']['config']);
        self::assertSame(1, $data['extension']['facade_ext']);
        self::assertSame(1, $data['extension']['oracle']);
        self::assertSame(1, $data['extension']['facade_extends']);
        self::assertSame(['Env::init', 'Server::branch'], $data['extension']['store_hooks']);
        self::assertTrue($data['extension']['exception_handler']);
        self::assertSame([], $data['blockers']);
    }

    /**
     * 非 ThinkPHP 项目应识别入口与 Composer 缺失。
     */
    public function testProfileOfNonFrameworkProject(): void
    {
        $root = $this->makeProject('non-tp');
        $this->write($root . '/index.php', '<?php \fxapp\Base::config("app.name");');
        $this->write($root . '/core/start.php', '<?php');
        $this->write($root . '/extend/fxri/data/app/store/common.php', '<?php');
        $this->write($root . '/extend/fxri/safe/autoload.php', '<?php');

        [$code, $output] = $this->runTool(['profile', '--root=' . $root, '--json']);
        self::assertSame(0, $code, $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('非 ThinkPHP（core/start.php）', $data['framework']);
        self::assertSame(['index.php', 'core/start.php'], $data['entry']);
        self::assertFalse($data['composer']['json']);
        self::assertFalse($data['composer']['autoload']);
        self::assertContains('缺少 Composer 接入，需先补 composer.json 与 vendor/autoload.php', $data['blockers']);
        self::assertContains('存在 safe / safe_local 模块，属独立工具，不在 fxyin 迁移范围', $data['notes']);
    }

    /**
     * 手写路由入口应判为非 ThinkPHP 的自定义入口。
     */
    public function testProfileOfCustomEntryProject(): void
    {
        $root = $this->makeProject('custom-entry');
        $this->write($root . '/index.php', '<?php $Urls = $_SERVER["REQUEST_URI"]; $Uri = explode("?", $Urls)[0];');
        $this->write($root . '/app/controller/Index.php', '<?php \fxapp\Base::config("app.name");');

        [$code, $output] = $this->runTool(['profile', '--root=' . $root, '--json']);
        self::assertSame(0, $code, $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame('非 ThinkPHP（自定义入口）', $data['framework']);
        self::assertSame(['index.php'], $data['entry']);
    }

    /**
     * 扫描应统计旧 API，并排除框架本体目录。
     */
    public function testScanCountsOldApiAndSkipsFrameworkBase(): void
    {
        $root = $this->makeProject('scan');
        $this->write($root . '/app/controller/Index.php', '<?php \fxapp\Base::config("app.name"); \fxapp\Param::define([], "1.2.3"); is_blank($x); fxy_json($x, "encode"); $echo[2];');
        $this->write($root . '/extend/fxri/base/autoload.php', '<?php \fxapp\Base::config("base"); \fxapp\Base::config("base");');
        $this->write($root . '/extend/fxri/data/app/store/common.php', '<?php \fxyin\Env::init(); \fxapp\Server::branch();');

        [$code, $output] = $this->runTool(['scan', '--root=' . $root, '--json']);
        self::assertSame(0, $code, $output);
        $data = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertSame(3, $data['counts']['fxapp']);
        self::assertSame(1, $data['counts']['echo_indexes']);
        self::assertSame(1, $data['counts']['dot_codes']);
        self::assertSame(1, $data['counts']['is_functions']);
        self::assertSame(1, $data['counts']['fxy_functions']);
        self::assertSame(1, $data['byDirectory']['extend/fxri/data']);
        self::assertArrayHasKey('Base::config', array_column($data['topMethods'], 'count', 'method'));
    }

    /**
     * 应用目录、命名空间、扩展目录与全局函数应取自项目配置。
     */
    public function testLayoutFollowsProjectConfig(): void
    {
        $root = $this->makeProject('layout');
        $this->write($root . '/extend/fxri/base/config.php', <<<'PHP'
            <?php
            return [
                'env' => [
                    'facade' => [0 => '\\fxbase\\facade\\'],
                ],
                'loader' => [
                    'fxapp' => [0 => __DIR__ . '/app/controller/'],
                ],
            ];
            PHP);
        $this->write($root . '/extend/fxri/data/config.php', <<<'PHP'
            <?php
            $root = dirname(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;
            return [
                'env' => [
                    'base' => ['app' => $root . 'apps' . DIRECTORY_SEPARATOR],
                    'facade' => [-1 => '\\fxbase\\facade_ext\\'],
                ],
                'loader' => [
                    'fxapp' => [1 => __DIR__ . '/app/controller/'],
                ],
            ];
            PHP);
        $this->write($root . '/extend/fxri/base/app/controller/facade/Base.php', '<?php class Base { public function extra() { return 1; } }');
        $this->write($root . '/extend/fxri/base/app/function/common.php', '<?php function is_custom($value) { return true; }');
        $this->write($root . '/extend/fxri/data/app/controller/facade_ext/Base.php', '<?php class Base {}');
        $this->write($root . '/apps/controller/Index.php', '<?php \fxbase\Base::config("x"); \fxbase\Base::extra(); \fxbase\facade_ext\Base::special(); is_custom($x);');

        [$profileCode, $profileOutput] = $this->runTool(['profile', '--root=' . $root, '--json']);
        self::assertSame(0, $profileCode, $profileOutput);
        $profile = json_decode($profileOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($profile);
        $layout = is_array($profile['layout'] ?? null) ? $profile['layout'] : [];
        self::assertSame('apps', $layout['app']);
        self::assertSame('fxbase', $layout['namespace']);
        self::assertSame('extend/fxri/data/app/controller/facade_ext', $layout['extensionDirectory']);
        self::assertSame(1, $layout['facadeClasses']);
        self::assertSame(1, $layout['globals']);

        [$scanCode, $scanOutput] = $this->runTool(['scan', '--root=' . $root, '--json']);
        self::assertSame(0, $scanCode, $scanOutput);
        $scan = json_decode($scanOutput, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($scan);
        self::assertSame('fxbase', $scan['namespace']);
        self::assertSame(2, $scan['counts']['fxapp']);
        self::assertSame(1, $scan['counts']['facade_ext_refs']);
        self::assertSame(1, $scan['counts']['is_functions']);
        self::assertSame(['Base::extra' => 1], $scan['localOnlyMethods']);
        self::assertArrayHasKey('apps', $scan['byDirectory']);
    }

    /**
     * 基线应支持写入、通过校验与超限失败。
     */
    public function testBaselineWriteCheckAndViolation(): void
    {
        $root = $this->makeProject('baseline');
        $baseline = $root . '/upgrade-baseline.json';
        $file = $root . '/app/controller/Index.php';
        $this->write($file, '<?php \fxapp\Base::config("app.name");');

        [$writeCode, $writeOutput] = $this->runTool(['baseline', '--root=' . $root, '--write', '--baseline=' . $baseline]);
        self::assertSame(0, $writeCode, $writeOutput);
        self::assertFileExists($baseline);

        [$passCode, $passOutput] = $this->runTool(['baseline', '--root=' . $root, '--check', '--baseline=' . $baseline]);
        self::assertSame(0, $passCode, $passOutput);

        $this->write($file, '<?php \fxapp\Base::config("app.name"); \fxapp\Base::flat("x");');
        [$failCode, $failOutput] = $this->runTool(['baseline', '--root=' . $root, '--check', '--baseline=' . $baseline]);
        self::assertSame(1, $failCode, $failOutput);
        self::assertStringContainsString('旧 API 超出基线', $failOutput);
    }

    /**
     * 创建临时项目目录。
     *
     * @param string $name 项目名
     * @return string 目录
     */
    private function makeProject(string $name): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-inspect-' . $name . '-' . bin2hex(random_bytes(4));
        mkdir($directory, 0o777, true);
        $this->temporaryDirectories[] = $directory;

        return $directory;
    }

    /**
     * 写文件并自动创建父目录。
     *
     * @param string $path 文件路径
     * @param string $contents 内容
     * @return void
     */
    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o777, true);
        }
        file_put_contents($path, $contents);
    }

    /**
     * 调用工具。
     *
     * @param list<string> $arguments 参数
     * @return array{0: int, 1: string} 退出码与输出
     */
    private function runTool(array $arguments): array
    {
        $tool = dirname(__DIR__, 2) . '/tools/upgrade-inspect.php';
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tool);
        foreach ($arguments as $argument) {
            $command .= ' ' . escapeshellarg($argument);
        }
        $output = [];
        $code = 0;
        exec($command . ' 2>&1', $output, $code);

        return [$code, implode(PHP_EOL, $output)];
    }

    /**
     * 递归删除目录。
     *
     * @param string $directory 目录
     * @return void
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
}
