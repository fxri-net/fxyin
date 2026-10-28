<?php

declare(strict_types=1);

namespace Fxri\Translation;

use Fxri\Support\Type;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 语言翻译器。
 *
 * 只负责字符串词条与占位符替换，嵌套词条由 `Language` 读取；未命中时原样返回词条。
 */
final class Translator implements TranslatorInterface
{
    /**
     * 初始化。
     *
     * @param Catalog $catalog 语言目录
     * @param string $locale 当前语言
     * @param string $domain 默认域
     */
    private function __construct(
        private readonly Catalog $catalog,
        private readonly string $locale = Catalog::LOCALE,
        private readonly string $domain = Catalog::DOMAIN,
    ) {
    }

    /**
     * 返回默认域。
     *
     * @return string 域名
     */
    public function domain(): string
    {
        return $this->domain;
    }

    /**
     * 替换占位符。
     *
     * 数字索引参数按 `sprintf` 处理；关联索引同时支持 `%name%` 与 `{:name}` 两种写法。
     *
     * @param string $message 已取的译文
     * @param array<array-key, mixed> $parameters 占位符参数
     * @return string 替换结果
     */
    public static function format(string $message, array $parameters = []): string
    {
        if ([] === $parameters) {
            return $message;
        }

        if (0 === array_key_first($parameters)) {
            return sprintf($message, ...array_map(Type::toString(...), array_values($parameters)));
        }

        $pairs = [];
        foreach ($parameters as $key => $value) {
            $name = trim((string) $key, '%');
            $pairs['%' . $name . '%'] = Type::toString($value);
            $pairs['{:' . $name . '}'] = Type::toString($value);
        }

        return strtr($message, $pairs);
    }

    /**
     * 取译文。
     *
     * 只返回字符串词条，嵌套数组按未命中处理。
     *
     * @param string $id 词条
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return string 译文，未命中时为词条本身
     */
    public function get(string $id, ?string $domain = null, ?string $locale = null): string
    {
        $value = $this->catalog->get($id, $locale ?? $this->locale, $domain ?? $this->domain);

        return is_string($value) ? $value : $id;
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
     * 判断字符串词条是否存在。
     *
     * @param string $id 词条
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return bool 是否存在
     */
    public function has(string $id, ?string $domain = null, ?string $locale = null): bool
    {
        return is_string($this->catalog->get($id, $locale ?? $this->locale, $domain ?? $this->domain));
    }

    /**
     * 创建实例。
     *
     * @param Catalog $catalog 语言目录
     * @param string $locale 当前语言
     * @param string $domain 默认域
     * @return self 翻译器
     */
    public static function make(Catalog $catalog, string $locale = Catalog::LOCALE, string $domain = Catalog::DOMAIN): self
    {
        return new self($catalog, $locale, $domain);
    }

    /**
     * 取译文并替换占位符。
     *
     * @param string $id 词条
     * @param array<array-key, mixed> $parameters 占位符参数
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return string 译文
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return self::format($this->get($id, $domain, $locale), $parameters);
    }

    /**
     * 切换默认域，返回新实例。
     *
     * @param string $domain 域名
     * @return self 新翻译器
     */
    public function withDomain(string $domain): self
    {
        return new self($this->catalog, $this->locale, $domain);
    }

    /**
     * 切换语言，返回新实例。
     *
     * @param string $locale 语言标识
     * @return self 新翻译器
     */
    public function withLocale(string $locale): self
    {
        return new self($this->catalog, $locale, $this->domain);
    }
}
