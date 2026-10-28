<?php

declare(strict_types=1);

namespace Fxri\Tests\Translation;

use Fxri\Translation\Catalog;
use Fxri\Translation\Translator;
use PHPUnit\Framework\TestCase;

/**
 * 翻译器测试：占位符两种写法、sprintf 参数与语言切换。
 */
final class TranslatorTest extends TestCase
{
    /**
     * 关联参数同时支持 `%name%` 与 `{:name}`。
     */
    public function testReplaceNamedPlaceholders(): void
    {
        $translator = $this->makeTranslator();

        self::assertSame('欢迎来到 风音', $translator->trans('welcome', ['name' => '风音']));
        self::assertSame('欢迎来到 风音', $translator->trans('brace', ['name' => '风音']));
        self::assertSame('欢迎来到 风音', $translator->trans('welcome', ['%name%' => '风音']));
        self::assertSame('欢迎来到 3', $translator->trans('welcome', ['name' => 3]));
    }

    /**
     * 数字索引参数按 sprintf 处理。
     */
    public function testReplaceNumericPlaceholders(): void
    {
        $translator = $this->makeTranslator();

        self::assertSame('共 3 条', $translator->trans('count', [3]));
    }

    /**
     * 未命中与嵌套词条都原样返回词条名。
     */
    public function testMissingReturnsId(): void
    {
        $translator = $this->makeTranslator();

        self::assertSame('unknown', $translator->trans('unknown'));
        self::assertFalse($translator->has('unknown'));
        self::assertFalse($translator->has('lang_list'));
    }

    /**
     * 语言与域都可切换，原实例保持不变。
     */
    public function testSwitchLocaleAndDomain(): void
    {
        $catalog = Catalog::make([
            'zh-cn' => ['messages' => ['delete' => '删除']],
            'en-us' => ['messages' => ['delete' => 'delete'], 'extra' => ['delete' => 'remove']],
        ]);
        $translator = Translator::make($catalog);

        self::assertSame('删除', $translator->trans('delete'));
        self::assertSame('delete', $translator->withLocale('en-us')->trans('delete'));
        self::assertSame('remove', $translator->withDomain('extra')->withLocale('en-us')->trans('delete'));
        self::assertSame('zh-cn', $translator->getLocale());
        self::assertSame('messages', $translator->domain());
    }

    /**
     * 建立测试用翻译器。
     *
     * @return Translator 翻译器
     */
    private function makeTranslator(): Translator
    {
        return Translator::make(Catalog::make([
            'zh-cn' => ['messages' => [
                'welcome' => '欢迎来到 %name%',
                'brace' => '欢迎来到 {:name}',
                'count' => '共 %d 条',
                'lang_list' => ['zh-cn' => '中文（简体）'],
            ]],
        ]));
    }
}
