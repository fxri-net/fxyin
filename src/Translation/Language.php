<?php

declare(strict_types=1);

namespace Fxri\Translation;

use Fxri\Http\Cookie\CookieJar;
use Fxri\Http\ServerContext;
use Fxri\Support\Type;

/**
 * 语言服务。
 *
 * 组合语言目录、翻译器与检测器，承担 1.6.1 `Lang` 的取词、嵌套词条、检测与语言切换能力。
 */
final class Language
{
    /**
     * 初始化。
     *
     * @param Catalog $catalog 语言目录
     * @param LocaleResolver $resolver 语言检测器
     * @param string $locale 当前语言
     */
    private function __construct(
        private readonly Catalog $catalog,
        private readonly LocaleResolver $resolver,
        private readonly string $locale = Catalog::LOCALE,
    ) {
    }

    /**
     * 返回当前语言的全部词条。
     *
     * @param string|null $locale 语言，缺省为当前语言
     * @return array<array-key, mixed> 词条
     */
    public function all(?string $locale = null): array
    {
        return $this->catalog->all($locale ?? $this->locale);
    }

    /**
     * 返回语言目录。
     *
     * @return Catalog 语言目录
     */
    public function catalog(): Catalog
    {
        return $this->catalog;
    }

    /**
     * 检测语言并切换，返回新实例。
     *
     * @param array<array-key, mixed>|null $query 查询参数，缺省读全局
     * @param CookieJar|null $cookies Cookie 容器，缺省读全局
     * @param ServerContext|null $server 服务端上下文，缺省读全局
     * @return self 新语言服务
     */
    public function detect(?array $query = null, ?CookieJar $cookies = null, ?ServerContext $server = null): self
    {
        return $this->withLocale($this->resolver->resolve($query ?? $_GET, $cookies, $server));
    }

    /**
     * 取词条。
     *
     * 字符串词条会替换占位符；嵌套词条原样返回；未命中时原样返回名称。
     *
     * @param string|null $name 词条，null 表示取当前语言的全部词条
     * @param array<array-key, mixed> $vars 占位符参数
     * @param string|null $locale 语言，缺省为当前语言
     * @return mixed 词条内容
     */
    public function get(?string $name = null, array $vars = [], ?string $locale = null): mixed
    {
        $locale ??= $this->locale;

        if (null === $name) {
            return $this->catalog->all($locale);
        }

        $value = $this->catalog->get($name, $locale, null, $name);

        return is_string($value) ? Translator::format($value, $vars) : $value;
    }

    /**
     * 判断词条是否存在。
     *
     * @param string $name 词条
     * @param string|null $locale 语言，缺省为当前语言
     * @return bool 是否存在
     */
    public function has(string $name, ?string $locale = null): bool
    {
        return $this->catalog->has($name, $locale ?? $this->locale);
    }

    /**
     * 加载语言包文件，返回新实例。
     *
     * @param string|array<array-key, string> $file 文件路径
     * @param string|null $locale 词条归入的语言，缺省为当前语言
     * @param string $domain 词条归入的域
     * @return self 新语言服务
     */
    public function load(string|array $file, ?string $locale = null, string $domain = Catalog::DOMAIN): self
    {
        return new self($this->catalog->load($file, $locale ?? $this->locale, $domain), $this->resolver, $this->locale);
    }

    /**
     * 返回当前语言。
     *
     * @return string 语言标识
     */
    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * 创建实例。
     *
     * 未指定检测器时，按语言目录已登记的语言生成允许列表。
     *
     * @param Catalog $catalog 语言目录
     * @param LocaleResolver|null $resolver 语言检测器
     * @param string $locale 当前语言
     * @return self 语言服务
     */
    public static function make(Catalog $catalog, ?LocaleResolver $resolver = null, string $locale = Catalog::LOCALE): self
    {
        return new self($catalog, $resolver ?? LocaleResolver::make()->withAllowList($catalog->locales()), $locale);
    }

    /**
     * 拼接词条列表。
     *
     * 对应 1.6.1 的 `langList` 与 `langParse`：数组逐项取词后，中文直接相连，其它语言以空格分隔。
     *
     * @param string|array<array-key, mixed>|null $name 词条或词条列表
     * @param string|null $locale 语言，缺省为当前语言
     * @return mixed 拼接结果或原始词条
     */
    public function phrase(string|array|null $name, ?string $locale = null): mixed
    {
        $locale ??= $this->locale;

        if (null === $name) {
            return $this->catalog->all($locale);
        }

        if (!is_array($name)) {
            return $this->get($name, [], $locale);
        }

        $parts = [];
        foreach ($name as $item) {
            $value = is_array($item) ? $this->phrase($item, $locale) : $this->get(Type::toString($item), [], $locale);
            if (is_array($value)) {
                $value = $this->phrase($value, $locale);
            }
            $text = Type::toString($value);
            if ('' === $text) {
                continue;
            }
            $parts[] = $text;
        }

        return implode('zh-cn' === $locale ? '' : ' ', $parts);
    }

    /**
     * 返回语言检测器。
     *
     * @return LocaleResolver 语言检测器
     */
    public function resolver(): LocaleResolver
    {
        return $this->resolver;
    }

    /**
     * 写入词条，返回新实例。
     *
     * @param string|array<array-key, mixed> $name 词条或词条集合
     * @param mixed $value 词条值，批量写入时忽略
     * @param string|null $locale 语言，缺省为当前语言
     * @param string $domain 域
     * @return self 新语言服务
     */
    public function set(string|array $name, mixed $value = null, ?string $locale = null, string $domain = Catalog::DOMAIN): self
    {
        return new self(
            $this->catalog->set($name, $value, $locale ?? $this->locale, $domain),
            $this->resolver,
            $this->locale,
        );
    }

    /**
     * 翻译词条。
     *
     * @param string $id 词条
     * @param array<array-key, mixed> $parameters 占位符参数
     * @param string|null $domain 域，缺省为默认域
     * @param string|null $locale 语言，缺省为当前语言
     * @return string 译文
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator($locale, $domain)->trans($id, $parameters);
    }

    /**
     * 生成翻译器。
     *
     * @param string|null $locale 语言，缺省为当前语言
     * @param string|null $domain 域，缺省为默认域
     * @return Translator 翻译器
     */
    public function translator(?string $locale = null, ?string $domain = null): Translator
    {
        return Translator::make($this->catalog, $locale ?? $this->locale, $domain ?? Catalog::DOMAIN);
    }

    /**
     * 切换语言，返回新实例。
     *
     * @param string $locale 语言标识
     * @return self 新语言服务
     */
    public function withLocale(string $locale): self
    {
        return new self($this->catalog, $this->resolver, $locale);
    }

    /**
     * 指定语言检测器。
     *
     * @param LocaleResolver $resolver 语言检测器
     * @return self 新实例
     */
    public function withResolver(LocaleResolver $resolver): self
    {
        return new self($this->catalog, $resolver, $this->locale);
    }
}
