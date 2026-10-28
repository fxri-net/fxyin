<?php

declare(strict_types=1);

namespace Fxri;

use Fxri\Config\ConfigLoader;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 内核构建器。
 *
 * 每个配置方法都返回新实例，装配过程不留副作用，最终由 build() 产出不可变内核。
 */
final class KernelBuilder
{
    /**
     * 初始化。
     *
     * @param list<string> $configDirectories 配置目录
     * @param LoggerInterface|null $logger 日志
     * @param TranslatorInterface|null $translator 翻译器
     * @param object|null $httpClient HTTP 客户端
     * @param object|null $adapter 接入方适配器
     */
    private function __construct(
        private readonly array $configDirectories = [],
        private readonly ?LoggerInterface $logger = null,
        private readonly ?TranslatorInterface $translator = null,
        private readonly ?object $httpClient = null,
        private readonly ?object $adapter = null,
    ) {
    }

    /**
     * 按当前装配生成内核。
     *
     * @return Kernel 内核实例
     */
    public function build(): Kernel
    {
        return new Kernel(
            ConfigLoader::make(...$this->configDirectories)->load(),
            $this->logger,
            $this->translator,
            $this->httpClient,
            $this->adapter,
        );
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
        return new self(
            $this->configDirectories,
            $this->logger,
            $this->translator,
            $this->httpClient,
            $adapter,
        );
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

        return new self(
            [...$this->configDirectories, $directory],
            $this->logger,
            $this->translator,
            $this->httpClient,
            $this->adapter,
        );
    }

    /**
     * 指定 HTTP 客户端。
     *
     * @param object $client 客户端
     * @return self 新实例
     */
    public function withHttpClient(object $client): self
    {
        return new self(
            $this->configDirectories,
            $this->logger,
            $this->translator,
            $client,
            $this->adapter,
        );
    }

    /**
     * 指定日志。
     *
     * @param LoggerInterface $logger 日志
     * @return self 新实例
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return new self(
            $this->configDirectories,
            $logger,
            $this->translator,
            $this->httpClient,
            $this->adapter,
        );
    }

    /**
     * 指定翻译器。
     *
     * @param TranslatorInterface $translator 翻译器
     * @return self 新实例
     */
    public function withTranslator(TranslatorInterface $translator): self
    {
        return new self(
            $this->configDirectories,
            $this->logger,
            $translator,
            $this->httpClient,
            $this->adapter,
        );
    }
}
