<?php

declare(strict_types=1);

namespace Fxri\Tests\Tooling;

use Fxri\File\Filesystem;
use Fxri\Tooling\MetaGenerator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * IDE 元数据生成测试：覆盖渲染结构、排序与键序、文件比对与非法入参。
 */
final class MetaGeneratorTest extends TestCase
{
    /** 临时目录 */
    private string $directory;

    /**
     * 建立临时目录。
     */
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-meta-' . bin2hex(random_bytes(6));
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
     * 渲染结果包含命名空间包裹、override 调用与映射条目。
     */
    public function testRenderStructure(): void
    {
        $content = MetaGenerator::render([
            'App\Locator::get' => [
                'base' => 'App\Oracle\Base',
                'user' => '\App\Oracle\User',
            ],
        ]);

        $expected = <<<'PHP'
            <?php

            namespace PHPSTORM_META {
                override(\App\Locator::get(0), map([
                    'base' => \App\Oracle\Base::class,
                    'user' => \App\Oracle\User::class,
                ]));
            }

            PHP;

        self::assertSame($expected, $content);
    }

    /**
     * 整份映射为空时只留空的命名空间块。
     */
    public function testRenderEmptyRegistries(): void
    {
        $expected = <<<'PHP'
            <?php

            namespace PHPSTORM_META {
            }

            PHP;

        self::assertSame($expected, MetaGenerator::render([]));
    }

    /**
     * 单个工厂方法映射为空时输出 map([])，不留空块。
     */
    public function testRenderEmptyMapping(): void
    {
        self::assertStringContainsString(
            '    override(\App\Locator::get(0), map([]));',
            MetaGenerator::render(['App\Locator::get' => []])
        );
    }

    /**
     * 多个工厂方法各生成一条 override。
     */
    public function testRenderMultipleFactories(): void
    {
        $content = MetaGenerator::render([
            'App\Locator::get' => ['base' => 'App\Oracle\Base'],
            'App\Repository::make' => ['repo' => 'App\Oracle\Repo'],
        ]);

        self::assertSame(2, substr_count($content, 'override('));
        self::assertStringContainsString('    override(\App\Locator::get(0), map([', $content);
        self::assertStringContainsString('    override(\App\Repository::make(0), map([', $content);
    }

    /**
     * 映射键保持传入顺序，不参与排序。
     */
    public function testRenderKeepsKeyOrder(): void
    {
        $content = MetaGenerator::render([
            'App\Locator::get' => ['user' => 'App\Oracle\User', 'base' => 'App\Oracle\Base'],
        ]);

        self::assertLessThan(
            strpos($content, "'base' =>"),
            strpos($content, "'user' =>")
        );
    }

    /**
     * 工厂方法按名称排序，与传入顺序无关。
     */
    public function testRenderSortsFactories(): void
    {
        $content = MetaGenerator::render([
            'App\Repository::make' => ['repo' => 'App\Oracle\Repo'],
            'App\Locator::get' => ['base' => 'App\Oracle\Base'],
        ]);

        self::assertLessThan(
            strpos($content, 'Repository::make'),
            strpos($content, 'Locator::get')
        );
    }

    /**
     * 文件与映射一致时 check 返回 true。
     */
    public function testCheckMatches(): void
    {
        $registries = ['App\Locator::get' => ['base' => 'App\Oracle\Base', 'user' => 'App\Oracle\User']];
        $file = $this->path('meta.php');
        Filesystem::write($file, MetaGenerator::render($registries));

        self::assertTrue(MetaGenerator::check($file, $registries));
    }

    /**
     * 文件内容与映射不一致时 check 返回 false。
     */
    public function testCheckMismatch(): void
    {
        $registries = ['App\Locator::get' => ['base' => 'App\Oracle\Base']];
        $file = $this->path('meta.php');
        Filesystem::write($file, str_replace('App\Oracle\Base', 'App\Oracle\User', MetaGenerator::render($registries)));

        self::assertFalse(MetaGenerator::check($file, $registries));
    }

    /**
     * 文件不存在时 check 返回 false，不抛异常。
     */
    public function testCheckMissingFile(): void
    {
        self::assertFalse(MetaGenerator::check($this->path('absent.php'), []));
    }

    /**
     * 文件用 CRLF 行尾时仍判定一致。
     */
    public function testCheckIgnoresEol(): void
    {
        $registries = ['App\Locator::get' => ['base' => 'App\Oracle\Base']];
        $file = $this->path('meta.php');
        Filesystem::write($file, str_replace("\n", "\r\n", MetaGenerator::render($registries)));

        self::assertTrue(MetaGenerator::check($file, $registries));
    }

    /**
     * 非法入参抛出 InvalidArgumentException。
     *
     * @param array<string, array<string, string>> $registries 非法映射
     * @param string $fragment 异常文案片段
     */
    #[DataProvider('invalidProvider')]
    public function testRenderRejectsInvalidInput(array $registries, string $fragment): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($fragment);
        MetaGenerator::render($registries);
    }

    /**
     * 非法入参用例。
     *
     * @return array<string, array{array<string, array<string, string>>, string}> 用例集合
     */
    public static function invalidProvider(): array
    {
        return [
            '工厂方法全名为空串' => [['' => ['base' => 'App\Oracle\Base']], '不能为空'],
            '工厂方法名缺少分隔符' => [['App\Locator' => ['base' => 'App\Oracle\Base']], '类名::方法名'],
            '方法名为空串' => [['App\Locator::' => ['base' => 'App\Oracle\Base']], '工厂方法名不合法'],
            '工厂方法名分隔符过多' => [['App\Locator::get::base' => ['base' => 'App\Oracle\Base']], '类名::方法名'],
            '工厂类名含空格' => [['App\Loc ator::get' => ['base' => 'App\Oracle\Base']], '类名格式不合法'],
            '映射键为大写' => [['App\Locator::get' => ['Base' => 'App\Oracle\Base']], '小写短名'],
            '映射键为空串' => [['App\Locator::get' => ['' => 'App\Oracle\Base']], '小写短名'],
            '映射键含空格' => [['App\Locator::get' => ['base x' => 'App\Oracle\Base']], '小写短名'],
            '映射值为空串' => [['App\Locator::get' => ['base' => '']], '类名格式不合法'],
            '映射值含非法字符' => [['App\Locator::get' => ['base' => 'App\Or-acle\Base']], '类名格式不合法'],
        ];
    }

    /**
     * 文件路径为空串时 check 抛出 InvalidArgumentException。
     */
    public function testCheckRejectsEmptyFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('路径不能为空');
        MetaGenerator::check('  ', []);
    }

    /**
     * 拼接临时文件路径。
     *
     * @param string $name 文件名
     * @return string 完整路径
     */
    private function path(string $name): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $name;
    }
}
