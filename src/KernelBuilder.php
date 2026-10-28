<?php

declare(strict_types=1);

namespace Fxri;

use Fxri\Config\ConfigLoader;
use Fxri\Config\Store;
use Fxri\Translation\Catalog;
use Fxri\Translation\Language;
use Fxri\Translation\LocaleResolver;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 内核构建器。
 *
 * 每个配置方法都以克隆方式返回新实例，装配过程不留副作用；默认加载内置语言包，可显式关闭。
 */
final class KernelBuilder
{
    /** 内置资源目录 */
    private const RESOURCES = __DIR__ . '/../resources';

    /** @var list<string> 配置目录 */
    private array $configDirectories = [];

    /** 日志 */
    private ?LoggerInterface $logger = null;

    /** 翻译器 */
    private ?TranslatorInterface $translator = null;

    /** HTTP 客户端 */
    private ?object $httpClient = null;

    /** 接入方适配器 */
    private ?object $adapter = null;

    /** 是否加载内置语言包 */
    private bool $builtinLanguage = true;

    /** 是否加载内置配置 */
    private bool $defaultConfig = true;

    /** @var list<array{0: string, 1: string}> 项目语言目录与归属语言 */
    private array $languageDirectories = [];

    /** 显式指定的语言服务 */
    private ?Language $language = null;

    /** 默认语言 */
    private ?string $locale = null;

    /** 语言检测器 */
    private ?LocaleResolver $localeResolver = null;

    /**
     * 初始化。
     */
    private function __construct()
    {
    }

    /**
     * 关闭内置资源，仅使用显式装配的部分。
     *
     * @return self 新实例
     */
    public function bare(): self
    {
        $builder = clone $this;
        $builder->builtinLanguage = false;
        $builder->defaultConfig = false;

        return $builder;
    }

    /**
     * 按当前装配生成内核。
     *
     * @return Kernel 内核实例
     */
    public function build(): Kernel
    {
        $config = $this->buildConfig();

        return new Kernel(
            $config,
            $this->logger,
            $this->translator,
            $this->httpClient,
            $this->adapter,
            $this->buildLanguage($config),
        );
    }

    /**
     * 组装配置存储。
     *
     * 内置配置先加载，项目配置目录按登记顺序覆盖。
     *
     * @return Store 配置存储
     */
    private function buildConfig(): Store
    {
        $directories = $this->configDirectories;
        if ($this->defaultConfig) {
            array_unshift($directories, self::RESOURCES . '/config');
        }

        return Store::make(ConfigLoader::make(...$directories)->load());
    }

    /**
     * 组装语言服务。
     *
     * 内置语言包先加载，项目语言目录按登记顺序覆盖；默认语言与允许列表取自配置，
     * 显式指定的语言服务与检测器优先。两者都未启用时返回 null。
     *
     * @param Store $config 配置存储
     * @return Language|null 语言服务
     */
    private function buildLanguage(Store $config): ?Language
    {
        if (null !== $this->language) {
            return $this->language;
        }

        if (!$this->builtinLanguage && [] === $this->languageDirectories) {
            return null;
        }

        $catalog = $this->builtinLanguage ? Catalog::fromRoot(self::RESOURCES . '/language') : Catalog::make();

        foreach ($this->languageDirectories as [$directory, $locale]) {
            $catalog = $catalog->loadDirectory($directory, $locale);
        }

        $locale = $this->locale ?? self::configString($config, 'app.lang.default', Catalog::LOCALE);
        $resolver = $this->localeResolver ?? LocaleResolver::make(
            cookieName: self::configString($config, 'app.lang.name', 'lang'),
            allowList: $this->allowList($config, $catalog),
            locale: $locale,
        );

        return Language::make($catalog, $resolver, $locale);
    }

    /**
     * 创建构建器。
     *
     * @return self 构建器
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * 指定接入方适配器。
     *
     * @param object $adapter 适配器
     * @return self 新实例
     */
    public function withAdapter(object $adapter): self
    {
        $builder = clone $this;
        $builder->adapter = $adapter;

        return $builder;
    }

    /**
     * 追加配置目录，后追加的目录覆盖先前的同名配置。
     *
     * @param string $directory 配置目录
     * @return self 新实例
     */
    public function withConfigDir(string $directory): self
    {
        // 路径拼写或部署遗漏在装配阶段暴露，避免运行期读到空配置
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('配置目录不存在：' . $directory);
        }

        $builder = clone $this;
        $builder->configDirectories[] = $directory;

        return $builder;
    }

    /**
     * 指定 HTTP 客户端。
     *
     * @param object $client 客户端
     * @return self 新实例
     */
    public function withHttpClient(object $client): self
    {
        $builder = clone $this;
        $builder->httpClient = $client;

        return $builder;
    }

    /**
     * 指定语言服务。
     *
     * 指定后不再组装内置或项目语言目录，语言内容完全由调用方决定。
     *
     * @param Language $language 语言服务
     * @return self 新实例
     */
    public function withLanguage(Language $language): self
    {
        $builder = clone $this;
        $builder->language = $language;

        return $builder;
    }

    /**
     * 追加项目语言目录，后追加的目录覆盖先前的同名词条。
     *
     * @param string $directory 语言包目录
     * @param string $locale 目录对应的语言
     * @return self 新实例
     */
    public function withLanguageDir(string $directory, string $locale = Catalog::LOCALE): self
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('语言目录不存在：' . $directory);
        }

        $builder = clone $this;
        $builder->languageDirectories[] = [$directory, $locale];

        return $builder;
    }

    /**
     * 指定默认语言。
     *
     * @param string $locale 语言标识
     * @return self 新实例
     */
    public function withLocale(string $locale): self
    {
        $builder = clone $this;
        $builder->locale = $locale;

        return $builder;
    }

    /**
     * 指定语言检测器。
     *
     * @param LocaleResolver $resolver 语言检测器
     * @return self 新实例
     */
    public function withLocaleResolver(LocaleResolver $resolver): self
    {
        $builder = clone $this;
        $builder->localeResolver = $resolver;

        return $builder;
    }

    /**
     * 指定日志。
     *
     * @param LoggerInterface $logger 日志
     * @return self 新实例
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $builder = clone $this;
        $builder->logger = $logger;

        return $builder;
    }

    /**
     * 指定翻译器。
     *
     * 指定后内核翻译走该实例，语言服务仍可单独读取。
     *
     * @param TranslatorInterface $translator 翻译器
     * @return self 新实例
     */
    public function withTranslator(TranslatorInterface $translator): self
    {
        $builder = clone $this;
        $builder->translator = $translator;

        return $builder;
    }

    /**
     * 关闭内置语言包。
     *
     * @return self 新实例
     */
    public function withoutBuiltinLanguage(): self
    {
        $builder = clone $this;
        $builder->builtinLanguage = false;

        return $builder;
    }

    /**
     * 关闭内置配置。
     *
     * @return self 新实例
     */
    public function withoutDefaultConfig(): self
    {
        $builder = clone $this;
        $builder->defaultConfig = false;

        return $builder;
    }

    /**
     * 取允许语言列表。
     *
     * 配置 `app.lang.list` 为字符串数组时使用配置值，否则回落到语言目录已登记的语言。
     *
     * @param Store $config 配置存储
     * @param Catalog $catalog 语言目录
     * @return list<string> 允许语言
     */
    private function allowList(Store $config, Catalog $catalog): array
    {
        $list = $config->get('app.lang.list');

        return is_array($list) ? array_values(array_filter($list, 'is_string')) : $catalog->locales();
    }

    /**
     * 读取字符串配置。
     *
     * @param Store $config 配置存储
     * @param string $name 点号路径
     * @param string $default 缺失或非字符串时的默认值
     * @return string 配置值
     */
    private static function configString(Store $config, string $name, string $default): string
    {
        $value = $config->get($name);

        return is_string($value) && '' !== $value ? $value : $default;
    }
}
