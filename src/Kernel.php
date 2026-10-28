<?php

declare(strict_types=1);

namespace Fxri;

use Fxri\Config\Config;
use Fxri\Config\Store;
use Fxri\Translation\ArrayTranslator;
use Fxri\Translation\Language;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 内核。
 *
 * 装配完成的不可变服务集合，只暴露读取入口；配置的只读视图与运行期覆盖分别由
 * `config()` 与 `configStore()` 提供。
 */
final class Kernel
{
    /** 配置 */
    private readonly Config $config;

    /** 配置存储 */
    private readonly Store $configStore;

    /** 日志 */
    private readonly LoggerInterface $logger;

    /** 翻译器 */
    private readonly ?TranslatorInterface $translator;

    /** HTTP 客户端 */
    private readonly ?object $httpClient;

    /** 接入方适配器 */
    private readonly ?object $adapter;

    /** 语言服务 */
    private readonly ?Language $language;

    /**
     * 初始化。
     *
     * 日志缺省时落为无操作实现；翻译器缺省时取语言服务，二者都缺省时落为数组翻译器。
     *
     * @param Config|Store $config 配置或配置存储
     * @param LoggerInterface|null $logger 日志
     * @param TranslatorInterface|null $translator 翻译器
     * @param object|null $httpClient HTTP 客户端
     * @param object|null $adapter 接入方适配器
     * @param Language|null $language 语言服务
     */
    public function __construct(
        Config|Store $config,
        ?LoggerInterface $logger = null,
        ?TranslatorInterface $translator = null,
        ?object $httpClient = null,
        ?object $adapter = null,
        ?Language $language = null,
    ) {
        $this->configStore = $config instanceof Store ? $config : Store::make($config);
        $this->config = $this->configStore->base();
        $this->logger = $logger ?? new NullLogger();
        $this->translator = $translator;
        $this->httpClient = $httpClient;
        $this->adapter = $adapter;
        $this->language = $language;
    }

    /**
     * 取接入方适配器。
     *
     * @return object|null 适配器，未指定时为 null
     */
    public function adapter(): ?object
    {
        return $this->adapter;
    }

    /**
     * 取配置。
     *
     * @return Config 只读配置实例
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * 取配置存储。
     *
     * 存储以 `config()` 为基底，运行期写入只进覆盖层，不改基底。
     *
     * @return Store 配置存储
     */
    public function configStore(): Store
    {
        return $this->configStore;
    }

    /**
     * 取 HTTP 客户端。
     *
     * @return object|null 客户端，未指定时为 null
     */
    public function httpClient(): ?object
    {
        return $this->httpClient;
    }

    /**
     * 取语言服务。
     *
     * @return Language|null 语言服务，未装配内置语言且未显式指定时为 null
     */
    public function language(): ?Language
    {
        return $this->language;
    }

    /**
     * 取日志。
     *
     * @return LoggerInterface 日志实例
     */
    public function logger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * 翻译词条。
     *
     * @param string $id 词条
     * @param array<array-key, mixed> $parameters 占位符参数
     * @return string 译文
     */
    public function trans(string $id, array $parameters = []): string
    {
        return $this->translator()->trans($id, $parameters);
    }

    /**
     * 取翻译器。
     *
     * 优先返回显式注入的翻译器，其次取语言服务的翻译器，都没有时落为数组翻译器。
     *
     * @return TranslatorInterface 翻译器
     */
    public function translator(): TranslatorInterface
    {
        return $this->translator ?? $this->language?->translator() ?? ArrayTranslator::make();
    }
}
