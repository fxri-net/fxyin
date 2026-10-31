<?php

declare(strict_types=1);

namespace Fxri\Tests\Parity;

use Fxri\Translation\Catalog;
use Fxri\Translation\Language;
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
     * 哈希取自 v1.6.1 的 LF 原始字节；资源目录已在 `.gitattributes` 固定 `eol=lf`，
     * 校验前同时拒绝 CRLF，避免平台自动转换造成本地与 CI 结果不一致。
     *
     * @var array<string, string>
     */
    private const HASHES = [
        'en-us/base.php' => '745724f3f99ba0b1e92ae65fc40dac4fdd49f942640a2f9300b7bdc24306cccd',
        'en-us/env.php' => 'd7348e782861226bd70d0733d4fb9afb2876afb210badaa58dbd9000d9d48611',
        'en-us/phrase.php' => 'e89b5397181514a2993a99ffc7f2b6c2f427fe5e8e59ae3ceb84d7441609ebec',
        'zh-cn/base.php' => 'd086d9b540b15e2f066001b9d58a7e6f48c3152107e7196a7412eb6fd9894769',
        'zh-cn/env.php' => '411186aba9f1b8d65892e47ff76c129756d2304496c2e4b198b2753e0ec04ae7',
        'zh-cn/phrase.php' => '487e5adc69f16d63816a63fe687945f99ab48b7724e27dab217f98b4e6e3f8e9',
    ];

    /**
     * 逐文件校验行尾与哈希。
     */
    public function testLegacyFilesAreByteIdentical(): void
    {
        foreach (self::HASHES as $file => $hash) {
            $contents = file_get_contents($this->root() . '/' . $file);
            self::assertIsString($contents, $file);
            self::assertStringNotContainsString("\r", $contents, $file);
            self::assertSame($hash, hash('sha256', $contents), $file);
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
     * 语言资源目录。
     *
     * @return string 目录路径
     */
    private function root(): string
    {
        return dirname(__DIR__, 2) . '/resources/language';
    }
}
