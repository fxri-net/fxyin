<?php

declare(strict_types=1);

namespace Fxri\Tests\Tooling;

use Fxri\File\Filesystem;
use Fxri\Tooling\SkillInstaller;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 技能薄壳安装测试：覆盖安装内容、幂等、同名产物保护与校验结果。
 */
final class SkillInstallerTest extends TestCase
{
    /** 临时目录 */
    private string $directory;

    /**
     * 建立临时目录。
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-skill-' . bin2hex(random_bytes(6));
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
     * 安装为包内每个技能生成一份薄壳，内容含标记、名称与真源位置。
     */
    public function testInstallWritesShells(): void
    {
        $result = SkillInstaller::install($this->directory);

        self::assertSame([], $result['skipped']);
        self::assertCount(count($this->skillNames()), $result['installed']);
        foreach ($this->skillNames() as $name) {
            $file = $this->shellPath($name);
            self::assertFileExists($file);

            $contents = Filesystem::read($file);
            self::assertStringContainsString(SkillInstaller::MARKER, $contents);
            self::assertStringContainsString('name: ' . $name, $contents);
            self::assertStringContainsString('description: ', $contents);
            self::assertStringContainsString('真源在 `', $contents);
            // 薄壳只写入口，不复制真源正文
            self::assertStringNotContainsString('## 何时使用', $contents);
        }
    }

    /**
     * 重复安装覆盖本包生成的薄壳，写入数量保持稳定。
     */
    public function testInstallIsIdempotent(): void
    {
        SkillInstaller::install($this->directory);
        $second = SkillInstaller::install($this->directory);

        self::assertCount(count($this->skillNames()), $second['installed']);
        self::assertSame([], $second['skipped']);
        self::assertTrue(SkillInstaller::check($this->directory));
    }

    /**
     * 同名文件不带本包标记时保留原内容并记入跳过。
     */
    public function testInstallKeepsForeignSameNameFile(): void
    {
        $name = $this->skillNames()[0];
        $file = $this->shellPath($name);
        Filesystem::write($file, "# 项目自有技能\n");

        $result = SkillInstaller::install($this->directory);

        self::assertSame("# 项目自有技能\n", Filesystem::read($file));
        self::assertContains($file, $result['skipped']);
    }

    /**
     * 安装完成后校验通过。
     */
    public function testCheckMatchesAfterInstall(): void
    {
        SkillInstaller::install($this->directory);

        self::assertTrue(SkillInstaller::check($this->directory));
    }

    /**
     * 薄壳缺失时校验失败。
     */
    public function testCheckFailsOnMissingShell(): void
    {
        SkillInstaller::install($this->directory);
        unlink($this->shellPath($this->skillNames()[0]));

        self::assertFalse(SkillInstaller::check($this->directory));
    }

    /**
     * 薄壳内容与真源漂移时校验失败。
     */
    public function testCheckFailsOnDrift(): void
    {
        SkillInstaller::install($this->directory);
        $file = $this->shellPath($this->skillNames()[0]);
        Filesystem::write($file, str_replace(SkillInstaller::MARKER, '<!-- 旧标记 -->', Filesystem::read($file)));

        self::assertFalse(SkillInstaller::check($this->directory));
    }

    /**
     * 多次声明的目标目录各生成一份薄壳。
     */
    public function testInstallWritesAllTargets(): void
    {
        $result = SkillInstaller::install($this->directory, ['.agents/skills', '.trae/skills']);

        self::assertCount(2 * count($this->skillNames()), $result['installed']);
        self::assertTrue(SkillInstaller::check($this->directory, ['.agents/skills', '.trae/skills']));
    }

    /**
     * 目标目录越出项目根时抛出 InvalidArgumentException。
     *
     * @param string $target 非法目标目录
     */
    #[DataProvider('invalidTargetProvider')]
    public function testInstallRejectsInvalidTarget(string $target): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('目标目录须为');

        SkillInstaller::install($this->directory, [$target]);
    }

    /**
     * 非法目标目录用例。
     *
     * @return array<string, array{string}> 用例集合
     */
    public static function invalidTargetProvider(): array
    {
        return [
            '空串' => [''],
            '绝对路径' => ['/abs/skills'],
            '上级目录' => ['../skills'],
            '中段上级目录' => ['.agents/../skills'],
            '带盘符' => ['C:/skills'],
        ];
    }

    /**
     * 项目根目录为空时抛出 InvalidArgumentException。
     */
    public function testRejectsEmptyRoot(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('项目根目录不能为空');

        SkillInstaller::install('  ');
    }

    /**
     * 列出包内技能名。
     *
     * @return list<string> 技能目录名
     */
    private function skillNames(): array
    {
        $skills = [];
        foreach (glob(dirname(__DIR__, 2) . '/skills/*/SKILL.md') ?: [] as $file) {
            $skills[] = basename(dirname($file));
        }

        return $skills;
    }

    /**
     * 拼接默认目标目录下的薄壳路径。
     *
     * @param string $name 技能名
     * @return string 完整路径
     */
    private function shellPath(string $name): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . '.agents' . DIRECTORY_SEPARATOR . 'skills' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'SKILL.md';
    }
}
