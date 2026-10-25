<?php

declare(strict_types=1);

namespace Fxri\Tests;

use Fxri\File\Filesystem;
use Fxri\KernelBuilder;
use Fxri\Translation\ArrayTranslator;
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
