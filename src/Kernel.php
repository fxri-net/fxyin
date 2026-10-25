<?php

declare(strict_types=1);

namespace Fxri;

use Fxri\Config\Config;
use Fxri\Translation\ArrayTranslator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 内核。
 *
 * 装配完成的不可变服务集合，只暴露读取入口，不提供任何修改方法。
 */
final class Kernel
{
    /** 配置 */
    private readonly Config $config;

    /** 日志 */
    private readonly LoggerInterface $logger;

    /** 翻译器 */
    private readonly TranslatorInterface $translator;

    /** HTTP 客户端 */
    private readonly ?object $httpClient;

    /** 接入方适配器 */
    private readonly ?object $adapter;

    /**
     * 初始化。
     *
     * 日志与翻译器缺省时落为无操作实现，保证内核持有的服务永不为空。
     *
     * @param Config $config 配置
     * @param LoggerInterface|null $logger 日志
     * @param TranslatorInterface|null $translator 翻译器
     * @param object|null $httpClient HTTP 客户端
     * @param object|null $adapter 接入方适配器
     */
    public function __construct(
        Config $config,
        ?LoggerInterface $logger = null,
        ?TranslatorInterface $translator = null,
        ?object $httpClient = null,
        ?object $adapter = null,
    ) {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->translator = $translator ?? ArrayTranslator::make();
        $this->httpClient = $httpClient;
        $this->adapter = $adapter;
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
     * @return Config 配置实例
     */
    public function config(): Config
    {
        return $this->config;
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
        return $this->translator->trans($id, $parameters);
    }

    /**
     * 取翻译器。
     *
     * @return TranslatorInterface 翻译器
     */
    public function translator(): TranslatorInterface
    {
        return $this->translator;
    }
}
