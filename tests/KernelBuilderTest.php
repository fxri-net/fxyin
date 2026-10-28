<?php

declare(strict_types=1);

namespace Fxri\Tests;

use Fxri\Config\Store;
use Fxri\File\Filesystem;
use Fxri\KernelBuilder;
use Fxri\Translation\ArrayTranslator;
use Fxri\Translation\Catalog;
use Fxri\Translation\Language;
use Fxri\Translation\Translator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * 组合根测试：装配不可变性与配置叠加顺序。
 */
final class KernelBuilderTest extends TestCase
{
    /** 临时目录集合 */
    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            Filesystem::deleteDirectory($directory);
        }
    }

    /**
     * 内核翻译走注入的翻译器，未注入的客户端与适配器为空。
     */
    public function testTrans(): void
    {
        $kernel = KernelBuilder::make()
            ->withTranslator(ArrayTranslator::make(['zh-cn' => ['messages' => ['greet' => '你好 %name%']]]))
            ->build();

        self::assertSame('你好 风音', $kernel->trans('greet', ['name' => '风音']));
        self::assertNull($kernel->adapter());
        self::assertNull($kernel->httpClient());
    }

    /**
     * 配置目录按登记顺序叠加，后者覆盖同键、保留异键。
     */
    public function testWithConfigDirOverrides(): void
    {
        $base = $this->makeDirectory('app.php', "<?php\nreturn ['name' => 'base', 'debug' => true];\n");
        $extra = $this->makeDirectory('app.php', "<?php\nreturn ['name' => 'override'];\n");

        $config = KernelBuilder::make()->withConfigDir($base)->withConfigDir($extra)->build()->config();

        self::assertSame('override', $config->get('app.name'));
        self::assertTrue($config->get('app.debug'));
    }

    /**
     * 配置目录不存在时立即失败，避免静默得到空配置。
     */
    public function testWithConfigDirRejectsMissingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('配置目录不存在');

        KernelBuilder::make()->withConfigDir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-missing-' . bin2hex(random_bytes(6)));
    }

    /**
     * 指定日志后产生新构建器，原构建器仍落为无操作日志。
     */
    public function testWithLoggerIsImmutable(): void
    {
        $builder = KernelBuilder::make();
        $logger = new class () extends AbstractLogger {
            /**
             * 记录日志。
             *
             * @param mixed $level 级别
             * @param string|\Stringable $message 消息
             * @param array<array-key, mixed> $context 上下文
             * @return void
             */
            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
        };

        $configured = $builder->withLogger($logger);

        self::assertNotSame($builder, $configured);
        self::assertInstanceOf(NullLogger::class, $builder->build()->logger());
        self::assertSame($logger, $configured->build()->logger());
    }

    /**
     * 默认装配内置语言包，中英文词条都可用。
     */
    public function testBuiltinLanguage(): void
    {
        $kernel = KernelBuilder::make()->build();
        $language = $kernel->language();

        self::assertInstanceOf(Language::class, $language);
        self::assertInstanceOf(Translator::class, $kernel->translator());
        self::assertSame('欢迎来到', $kernel->trans('welcome to'));
        self::assertSame('删除', $language->get('delete'));
        self::assertSame('delete', $language->withLocale('en-us')->get('delete'));
        self::assertSame(['zh-cn' => '中文（简体）', 'en-us' => '英语（美国）'], $language->get('lang_list'));
    }

    /**
     * 关闭内置语言后不装配语言服务，翻译回落为数组翻译器。
     */
    public function testWithoutBuiltinLanguage(): void
    {
        $kernel = KernelBuilder::make()->withoutBuiltinLanguage()->build();

        self::assertNull($kernel->language());
        self::assertInstanceOf(ArrayTranslator::class, $kernel->translator());
        self::assertSame('welcome to', $kernel->trans('welcome to'));
    }

    /**
     * `bare()` 与关闭内置语言等价。
     */
    public function testBareDisablesBuiltinLanguage(): void
    {
        self::assertNull(KernelBuilder::make()->bare()->build()->language());
    }

    /**
     * 项目语言目录覆盖内置词条，同时保留内置语言。
     */
    public function testWithLanguageDirOverrides(): void
    {
        $directory = $this->makeDirectory('custom.php', "<?php\nreturn ['delete' => '覆盖删除'];\n");

        $kernel = KernelBuilder::make()->withLanguageDir($directory)->build();

        self::assertSame('覆盖删除', $kernel->trans('delete'));
        self::assertSame('欢迎来到', $kernel->trans('welcome to'));
    }

    /**
     * 显式指定语言服务后不再组装内置语言。
     */
    public function testWithLanguage(): void
    {
        $language = Language::make(Catalog::make(['zh-cn' => ['messages' => ['greet' => '你好']]]));

        $kernel = KernelBuilder::make()->withLanguage($language)->build();

        self::assertSame($language, $kernel->language());
        self::assertSame('你好', $kernel->trans('greet'));
    }

    /**
     * 语言目录不存在时立即失败。
     */
    public function testWithLanguageDirRejectsMissingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('语言目录不存在');

        KernelBuilder::make()->withLanguageDir(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-missing-lang-' . bin2hex(random_bytes(6)));
    }

    /**
     * 默认装配内置配置，并作为配置存储的基底。
     */
    public function testBuiltinConfig(): void
    {
        $kernel = KernelBuilder::make()->build();
        $store = $kernel->configStore();

        self::assertInstanceOf(Store::class, $store);
        self::assertSame('PRC', $store->get('app.base.timezone'));
        self::assertSame('zh-cn', $store->get('app.lang.default'));
        self::assertSame('zh-cn', $kernel->config()->get('app.lang.default'));
        self::assertSame('master', $kernel->config()->get('branch.default'));
        self::assertSame('zh-cn', $kernel->config()->get('env.view.lang'));
    }

    /**
     * 项目配置覆盖内置配置，并影响默认语言。
     */
    public function testConfigOverridesBuiltin(): void
    {
        $directory = $this->makeDirectory('app.php', "<?php\nreturn ['lang' => ['default' => 'en-us']];\n");

        $kernel = KernelBuilder::make()->withConfigDir($directory)->build();

        self::assertSame('en-us', $kernel->config()->get('app.lang.default'));
        self::assertSame('en-us', $kernel->language()?->locale());
    }

    /**
     * 关闭内置配置后基底为空，语言仍按内置语言包装配。
     */
    public function testWithoutDefaultConfig(): void
    {
        $kernel = KernelBuilder::make()->withoutDefaultConfig()->build();

        self::assertSame([], $kernel->config()->all());
        self::assertSame('zh-cn', $kernel->language()?->locale());
    }

    /**
     * `bare()` 同时关闭内置配置与内置语言。
     */
    public function testBareDisablesBuiltinResources(): void
    {
        $kernel = KernelBuilder::make()->bare()->build();

        self::assertSame([], $kernel->config()->all());
        self::assertNull($kernel->language());
    }

    /**
     * 运行期写入只进覆盖层，只读配置保持不变。
     */
    public function testConfigStoreSet(): void
    {
        $kernel = KernelBuilder::make()->build();
        $kernel->configStore()->set('app.lang.default', 'en-us');

        self::assertSame('en-us', $kernel->configStore()->get('app.lang.default'));
        self::assertSame('zh-cn', $kernel->config()->get('app.lang.default'));
    }

    /**
     * 显式指定默认语言时优先于配置。
     */
    public function testWithLocaleOverridesConfig(): void
    {
        $kernel = KernelBuilder::make()->withLocale('en-us')->build();

        self::assertSame('en-us', $kernel->language()?->locale());
        self::assertSame('zh-cn', $kernel->config()->get('app.lang.default'));
    }

    /**
     * 建立含单个配置文件的临时目录。
     *
     * @param string $name 文件名
     * @param string $contents 文件内容
     * @return string 目录路径
     */
    private function makeDirectory(string $name, string $contents): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-config-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $contents);
        $this->directories[] = $directory;

        return $directory;
    }
}
