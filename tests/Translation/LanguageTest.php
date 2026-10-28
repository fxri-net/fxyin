<?php

declare(strict_types=1);

namespace Fxri\Tests\Translation;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\ServerContext;
use Fxri\Translation\Catalog;
use Fxri\Translation\Language;
use PHPUnit\Framework\TestCase;

/**
 * 语言服务测试：取词、嵌套词条、检测与不可变切换。
 */
final class LanguageTest extends TestCase
{
    /**
     * 字符串与嵌套词条分别处理，未命中原样返回。
     */
    public function testGet(): void
    {
        $language = $this->makeLanguage();

        self::assertSame('欢迎来到 风音', $language->get('welcome', ['name' => '风音']));
        self::assertSame(['zh-cn' => '中文（简体）'], $language->get('lang_list'));
        self::assertSame('unknown', $language->get('unknown'));
        self::assertTrue($language->has('lang_list'));
        self::assertFalse($language->has('unknown'));
    }

    /**
     * 取全部词条时按语言合并。
     */
    public function testAll(): void
    {
        $language = $this->makeLanguage();

        self::assertArrayHasKey('welcome', $language->all());
        self::assertArrayHasKey('fxyin', $language->get(null));
    }

    /**
     * 词条列表按语言拼接：中文相连，英文以空格分隔。
     */
    public function testPhrase(): void
    {
        $language = $this->makeLanguage();

        self::assertSame('风音删除', $language->phrase(['fxyin', 'delete']));
        self::assertSame('fxyin delete', $language->phrase(['fxyin', 'delete'], 'en-us'));
        self::assertSame('风音', $language->phrase('fxyin'));
    }

    /**
     * 直通与忽略标记按 1.x 语义生效，可选首字母大写。
     */
    public function testPhraseMarkersAndCapitalize(): void
    {
        $language = $this->makeLanguage();

        self::assertSame('Hello', $language->phrase('!@Hello'));
        self::assertSame('', $language->phrase('!#ignored'));
        self::assertSame('Hello风音', $language->phrase(['!@Hello', 'fxyin']));
        self::assertSame('风音删除', $language->phrase(['fxyin', '!#ignored', 'delete']));
        self::assertSame('Hello', $language->phrase('hello', null, true));
        self::assertSame('Hello world', $language->phrase(['hello', 'world'], 'en-us', true));

        $custom = $language->withPrefix('~')->withIgnore('#');
        self::assertSame('raw', $custom->phrase('~raw'));
        self::assertSame('', $custom->phrase('#ignored'));
        self::assertSame('!@Hello', $custom->phrase('!@Hello'));
    }

    /**
     * 检测后返回新实例，原实例语言不变。
     */
    public function testDetectIsImmutable(): void
    {
        $language = $this->makeLanguage();
        $detected = $language->detect(['lang' => 'en-us'], CookieJar::make(), ServerContext::make());

        self::assertSame('en-us', $detected->locale());
        self::assertSame('zh-cn', $language->locale());
    }

    /**
     * 写入与切换语言都返回新实例。
     */
    public function testSetAndWithLocaleAreImmutable(): void
    {
        $language = $this->makeLanguage();
        $written = $language->set('greet', '你好');

        self::assertSame('你好', $written->get('greet'));
        self::assertSame('greet', $language->get('greet'));
        self::assertSame('welcome to x', $language->withLocale('en-us')->get('welcome', ['name' => 'x']));
        self::assertSame('欢迎来到 x', $language->get('welcome', ['name' => 'x']));
    }

    /**
     * 建立测试用语言服务。
     *
     * @return Language 语言服务
     */
    private function makeLanguage(): Language
    {
        return Language::make(Catalog::make([
            'zh-cn' => ['messages' => [
                'welcome' => '欢迎来到 %name%',
                'fxyin' => '风音',
                'delete' => '删除',
                'lang_list' => ['zh-cn' => '中文（简体）'],
            ]],
            'en-us' => ['messages' => [
                'welcome' => 'welcome to %name%',
                'fxyin' => 'fxyin',
                'delete' => 'delete',
            ]],
        ]));
    }
}
