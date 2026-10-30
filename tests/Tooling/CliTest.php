<?php

declare(strict_types=1);

namespace Fxri\Tests\Tooling;

use Fxri\File\Filesystem;
use Fxri\Tooling\Cli;
use PHPUnit\Framework\TestCase;

/**
 * 命令行注册表与解析测试：覆盖可用域、随包域缺失与参数分类。
 */
final class CliTest extends TestCase
{
    /** 临时目录 */
    private string $directory;

    /**
     * 建立临时目录。
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-cli-' . bin2hex(random_bytes(6));
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
     * 无参数时给出总帮助。
     */
    public function testParseEmpty(): void
    {
        $plan = Cli::parse(['fxyin']);

        self::assertSame('help', $plan['action']);
        self::assertSame('', $plan['topic']);
        self::assertSame('', $plan['error']);
    }

    /**
     * help 后可带命令名。
     */
    public function testParseHelpTopic(): void
    {
        $plan = Cli::parse(['fxyin', 'help', 'upgrade']);

        self::assertSame('help', $plan['action']);
        self::assertSame('upgrade', $plan['topic']);
    }

    /**
     * 已注册命令转为执行，后续参数原样保留。
     */
    public function testParseCommandArgs(): void
    {
        $plan = Cli::parse(['fxyin', 'upgrade', 'profile', '--root=/tmp']);

        self::assertSame('run', $plan['action']);
        self::assertSame('upgrade', $plan['command']);
        self::assertSame(['profile', '--root=/tmp'], $plan['args']);
    }

    /**
     * 未知命令转成用法错误。
     */
    public function testParseUnknown(): void
    {
        $plan = Cli::parse(['fxyin', 'unknown']);

        self::assertSame('error', $plan['action']);
        self::assertSame('未知命令：unknown', $plan['error']);
    }

    /**
     * 变体帮助标记等价于 help。
     *
     * @param string $flag 帮助标记
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('helpFlagProvider')]
    public function testParseHelpFlags(string $flag): void
    {
        $plan = Cli::parse(['fxyin', $flag]);

        self::assertSame('help', $plan['action']);
    }

    /**
     * 帮助标记用例。
     *
     * @return array<string, array{string}> 用例集合
     */
    public static function helpFlagProvider(): array
    {
        return [
            'help' => ['help'],
            '-h' => ['-h'],
            '--help' => ['--help'],
        ];
    }

    /**
     * 总帮助列出全部可用命令与退出码约定。
     */
    public function testUsageListsCommands(): void
    {
        $usage = Cli::usage('fxyin', $this->packageRoot());

        foreach (['skills', 'upgrade', 'scan', 'package', 'parity', 'help'] as $name) {
            self::assertStringContainsString($name, $usage);
        }
        self::assertStringContainsString('退出码', $usage);
        self::assertStringContainsString('php fxyin <命令>', $usage);
    }

    /**
     * 缺少实现文件的域不进入可用列表。
     */
    public function testAvailableSkipsMissingScripts(): void
    {
        Filesystem::makeDirectory($this->directory . DIRECTORY_SEPARATOR . 'tools');
        Filesystem::write($this->directory . '/tools/upgrade-inspect.php', "<?php\n");
        Filesystem::write($this->directory . '/tools/no-numeric-code.php', "<?php\n");

        $available = Cli::available($this->directory);

        self::assertSame(['skills', 'upgrade', 'scan'], array_keys($available));
    }

    /**
     * 随包域的实现文件缺失时被列为异常，而不是静默隐藏。
     */
    public function testMissingShippedReportsMissing(): void
    {
        self::assertSame(['upgrade', 'scan'], Cli::missingShipped($this->directory));
    }

    /**
     * 包根目录下随包域都在位时没有异常。
     */
    public function testMissingShippedEmptyInPackage(): void
    {
        self::assertSame([], Cli::missingShipped($this->packageRoot()));
    }

    /**
     * 仓库根目录。
     *
     * @return string 绝对路径
     */
    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
