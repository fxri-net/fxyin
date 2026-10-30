<?php

declare(strict_types=1);

namespace Fxri\Tests\Tooling;

use Fxri\File\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * 入口集成测试：以子进程运行真实 fxyin，覆盖帮助、未知命令、扫描与技能薄壳。
 */
final class CliEntryTest extends TestCase
{
    /** 入口文件 */
    private string $entry;

    /** 临时目录 */
    private string $directory;

    /**
     * 记录入口路径并建立临时目录。
     */
    protected function setUp(): void
    {
        $this->entry = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'fxyin';
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-entry-' . bin2hex(random_bytes(6));
        Filesystem::makeDirectory($this->directory);
    }

    /**
     * 清理临时目录。
     */
    protected function tearDown(): void
    {
        Filesystem::deleteDirectory($this->directory);
    }

    /**
     * 无参数时输出总帮助并退出 0。
     */
    public function testHelpExitsZero(): void
    {
        $result = $this->run('');

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('fxyin', $result['output']);
        self::assertStringContainsString('skills', $result['output']);
    }

    /**
     * help 加命令名时给出该命令用法。
     */
    public function testHelpTopic(): void
    {
        $result = $this->run('help upgrade');

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('php fxyin upgrade profile', $result['output']);
    }

    /**
     * 未知命令以用法错误退出。
     */
    public function testUnknownCommandExitsTwo(): void
    {
        $result = $this->run('unknown');

        self::assertSame(2, $result['code']);
        self::assertStringContainsString('未知命令', $result['output']);
    }

    /**
     * 扫描命令透传到实现层并返回成功。
     */
    public function testScanDelegates(): void
    {
        $result = $this->run('scan');

        self::assertSame(0, $result['code']);
        self::assertStringContainsString('数字码扫描通过', $result['output']);
    }

    /**
     * 技能命令安装薄壳后校验通过。
     */
    public function testSkillsInstallThenCheck(): void
    {
        $root = escapeshellarg($this->directory);
        $install = $this->run('skills --root ' . $root);

        self::assertSame(0, $install['code'], $install['output']);
        self::assertFileExists($this->directory . '/.agents/skills/fxri-fxyin/SKILL.md');

        $check = $this->run('skills --root ' . $root . ' --check');

        self::assertSame(0, $check['code'], $check['output']);
    }

    /**
     * 未安装时校验以门禁失败退出。
     */
    public function testSkillsCheckFailsWhenMissing(): void
    {
        $result = $this->run('skills --root ' . escapeshellarg($this->directory) . ' --check');

        self::assertSame(1, $result['code']);
        self::assertStringContainsString('不一致', $result['output']);
    }

    /**
     * 运行入口并收集输出。
     *
     * @param string $arguments 命令行参数
     * @return array{code: int, output: string} 退出码与合并后的输出
     */
    private function run(string $arguments): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->entry);
        if ('' !== $arguments) {
            $command .= ' ' . $arguments;
        }
        $command .= ' 2>&1';

        $output = [];
        $code = 0;
        exec($command, $output, $code);

        return ['code' => $code, 'output' => implode("\n", $output)];
    }
}
