<?php

declare(strict_types=1);

namespace Fxri\Translation;

use Fxri\Support\Type;
use Fxri\Translation\Loader\PhpFileLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 数组翻译器。
 *
 * 词条按「语言 / 域 / 词条」三级组织，未命中时原样返回词条本身。
 */
final class ArrayTranslator implements TranslatorInterface
{
    /** 默认域 */
    public const DOMAIN = 'messages';

    /**
     * 初始化。
     *
     * @param array<string, array<string, array<string, string>>> $messages 词条集合
     * @param string $locale 默认语言
     */
    private function __construct(
        private readonly array $messages,
        private readonly string $locale,
    ) {
    }

    /**
     * 从语言包目录创建实例。
     *
     * @param string $directory 语言包目录
     * @param string $locale 目录对应的语言
     * @return self 翻译器
     */
    public static function fromDirectory(string $directory, string $locale = 'zh-cn'): self
    {
        return new self([$locale => [self::DOMAIN => PhpFileLoader::loadDirectory($directory)]], $locale);
    }

    /**
     * 取译文，未命中时返回词条本身。
     *
     * @param string $id 词条
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return string 译文
     */
    public function get(string $id, ?string $domain = null, ?string $locale = null): string
    {
        return $this->messages[$locale ?? $this->locale][$domain ?? self::DOMAIN][$id] ?? $id;
    }

    /**
     * 返回当前语言。
     *
     * @return string 语言标识
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * 判断词条是否存在。
     *
     * @param string $id 词条
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return bool 是否存在
     */
    public function has(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        return isset($this->messages[$locale ?? $this->locale][$domain ?? self::DOMAIN][$id]);
    }

    /**
     * 创建实例。
     *
     * @param array<string, array<string, array<string, string>>> $messages 词条集合
     * @param string $locale 默认语言
     * @return self 翻译器
     */
    public static function make(array $messages = [], string $locale = 'zh-cn'): self
    {
        return new self($messages, $locale);
    }

    /**
     * 取译文并替换占位符。
     *
     * 参数键可省略百分号，`['name' => '风音']` 与 `['%name%' => '风音']` 等价。
     *
     * @param string $id 词条
     * @param array<array-key, mixed> $parameters 占位符参数
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return string 译文
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->replace($this->get($id, $domain, $locale), $parameters);
    }

    /**
     * 切换语言，返回新实例。
     *
     * @param string $locale 语言标识
     * @return self 新实例
     */
    public function withLocale(string $locale): self
    {
        return new self($this->messages, $locale);
    }

    /**
     * 替换占位符。
     *
     * @param string $message 已取的译文
     * @param array<array-key, mixed> $parameters 占位符参数
     * @return string 替换结果
     */
    private function replace(string $message, array $parameters): string
    {
        if ([] === $parameters) {
            return $message;
        }

        $pairs = [];
        foreach ($parameters as $key => $value) {
            $pairs['%' . trim((string) $key, '%') . '%'] = Type::toString($value);
        }

        return strtr($message, $pairs);
    }
}
