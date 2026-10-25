<?php

declare(strict_types=1);

namespace Fxri\Tests\Translation;

use Fxri\File\Filesystem;
use Fxri\Translation\ArrayTranslator;
use PHPUnit\Framework\TestCase;

/**
 * 数组翻译器测试：占位符替换、缺失回退与语言切换。
 */
final class ArrayTranslatorTest extends TestCase
{
    /**
     * 从语言包目录创建实例，非字符串值被忽略。
     */
    public function testFromDirectory(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fxri-lang-' . bin2hex(random_bytes(6));
        mkdir($directory);
        file_put_contents($directory . '/phrase.php', "<?php\nreturn ['welcome to' => '欢迎来到', 'invalid' => 1];\n");

        try {
            $translator = ArrayTranslator::fromDirectory($directory);

            self::assertSame('欢迎来到', $translator->trans('welcome to'));
            self::assertFalse($translator->has('invalid'));
        } finally {
            Filesystem::deleteDirectory($directory);
        }
    }

    /**
     * 词条缺失时原样返回，默认语言为 zh-cn。
     */
    public function testMissingReturnsId(): void
    {
        $translator = ArrayTranslator::make();

        self::assertSame('unknown', $translator->trans('unknown'));
        self::assertSame('unknown', $translator->get('unknown'));
        self::assertFalse($translator->has('unknown'));
        self::assertSame('zh-cn', $translator->getLocale());
    }

    /**
     * 占位符按参数替换，键可带或不带百分号。
     */
    public function testTransReplacesPlaceholders(): void
    {
        $translator = ArrayTranslator::make(['zh-cn' => ['messages' => [
            'greet' => '欢迎来到 %name%',
            'count' => '共 %count% 条',
        ]]]);

        self::assertSame('欢迎来到 风音', $translator->trans('greet', ['name' => '风音']));
        self::assertSame('欢迎来到 风音', $translator->trans('greet', ['%name%' => '风音']));
        self::assertSame('共 3 条', $translator->trans('count', ['count' => 3]));
        self::assertSame('greet', $translator->trans('greet', [], 'other'));
    }

    /**
     * 切换语言返回新实例，原实例不变。
     */
    public function testWithLocaleIsImmutable(): void
    {
        $translator = ArrayTranslator::make([
            'zh-cn' => ['messages' => ['greet' => '你好']],
            'en-us' => ['messages' => ['greet' => 'Hello']],
        ]);

        self::assertSame('Hello', $translator->withLocale('en-us')->trans('greet'));
        self::assertSame('你好', $translator->trans('greet'));
        self::assertSame('zh-cn', $translator->getLocale());
    }
}
