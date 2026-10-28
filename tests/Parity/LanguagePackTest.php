<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\ServerContext;
use Fxri\Translation\Catalog;
use Fxri\Translation\Language;
use Fxri\Translation\LocaleResolver;
use Fxri\Translation\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 语言资源与行为对等测试。
 */
final class LanguagePackTest extends TestCase
{
    /**
     * 内置语言包与 v1.6.1 逐字节一致。
     *
     * 哈希取自 v1.6.1 的 app/language 原始文件，改动内置词条会直接使本用例失败。
     *
     * @var array<string, string>
     */
    private const HASHES = [
        'en-us/base.php' => 'dcbe7c907223c4dfb282e638553866343b7a747326256b39af9cae457dc10129',
        'en-us/env.php' => 'fb9fe44023037174d83f3fcfc8195c079af5db8cd56cd2639fdcda44f1227d60',
        'en-us/phrase.php' => 'b939ac9cd351f67465d04f9b145f6b995b4da07f99df6b6a9c94d54e416d7265',
        'zh-cn/base.php' => '9549d8c2fbeb08f54f1fab37d435929047f591cb11de69d3241543e31bd24272',
        'zh-cn/env.php' => '3cb6bd743eb1b6aa8ae087b15780837969ad42a383a1af9b89d57a9ea783fd8f',
        'zh-cn/phrase.php' => '29038ba2d81f871e2a4abbbc2ff1aa1d0a05255a4827afb2ab831723d82a0d85',
    ];

    /**
     * 逐文件校验哈希。
     */
    public function testLegacyFilesAreByteIdentical(): void
    {
        foreach (self::HASHES as $file => $hash) {
            self::assertSame($hash, hash_file('sha256', $this->root() . '/' . $file), $file);
        }
    }

    /**
     * 内置词条、嵌套语言列表与拼接行为保持 1.6.1 语义。
     */
    public function testBuiltinCatalog(): void
    {
        $language = Language::make(Catalog::fromRoot($this->root()));

        self::assertSame(['zh-cn' => '中文（简体）', 'en-us' => '英语（美国）'], $language->get('lang_list'));
        self::assertSame('删除', $language->get('delete'));
        self::assertSame('delete', $language->withLocale('en-us')->get('delete'));
        self::assertSame('404 无法找到', $language->get('http 404'));
        self::assertSame('不能为空', $language->phrase('cannot be empty'));
        self::assertSame('风音删除', $language->phrase(['fxyin', 'delete']));
    }

    /**
     * 占位符支持 `%name%`、`{:name}` 与 sprintf 数字参数。
     */
    public function testPlaceholders(): void
    {
        $translator = Translator::make(Catalog::make([
            'zh-cn' => ['messages' => [
                'percent' => '欢迎 %name%',
                'brace' => '欢迎 {:name}',
                'number' => '共 %d 条',
            ]],
        ]));

        self::assertSame('欢迎 风音', $translator->trans('percent', ['name' => '风音']));
        self::assertSame('欢迎 风音', $translator->trans('brace', ['name' => '风音']));
        self::assertSame('共 3 条', $translator->trans('number', [3]));
    }

    /**
     * 检测顺序与 Cookie 读取和 1.6.1 一致。
     */
    public function testDetection(): void
    {
        $resolver = LocaleResolver::make(allowList: ['zh-cn', 'en-us']);

        self::assertSame('en-us', $resolver->resolve(['lang' => 'EN-US'], CookieJar::make(), ServerContext::make()));
        self::assertSame('en-us', $resolver->resolve([], CookieJar::make(['lang' => 'en-us']), ServerContext::make()));
        self::assertSame('en-us', $resolver->resolve([], CookieJar::make(), ServerContext::make(['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9'])));
        self::assertSame('zh-cn', $resolver->resolve(['lang' => 'zh-hans-cn'], CookieJar::make(), ServerContext::make()));
    }

    /**
     * 语言资源目录。
     *
     * @return string 目录路径
     */
    private function root(): string
    {
        return dirname(__DIR__, 2) . '/resources/language';
    }
}
