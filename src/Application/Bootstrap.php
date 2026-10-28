<?php

declare(strict_types=1);

namespace Fxri\Application;

use Fxri\Kernel;
use Fxri\KernelBuilder;
use InvalidArgumentException;
use Throwable;

/**
 * 应用启动器。
 *
 * 按项目目录显式装配配置、语言、函数、服务与启动钩子，只在调用 `boot()` 时执行；
 * 不接管请求生命周期，也不提供全局入口。
 */
final class Bootstrap
{
    /** 加载阶段 */
    public const PHASE_LOAD = 'load';

    /** 启动阶段 */
    public const PHASE_START = 'start';

    /** 就绪阶段 */
    public const PHASE_READY = 'ready';

    /** 允许的阶段 */
    private const PHASES = [self::PHASE_LOAD, self::PHASE_START, self::PHASE_READY];

    /** @var list<string> 配置目录 */
    private array $configDirectories = [];

    /** @var list<array{0: string, 1: string}> 语言目录与归属语言 */
    private array $languageDirectories = [];

    /** @var list<string> 函数文件 */
    private array $functionFiles = [];

    /** @var list<string> 启动脚本 */
    private array $storeFiles = [];

    /** @var array<string, list<callable>> 阶段钩子 */
    private array $hooks = [];

    /** 服务注册表 */
    private readonly Services $services;

    /** 内核 */
    private ?Kernel $kernel = null;

    /** 是否已扫描目录 */
    private bool $scanned = false;

    /**
     * 初始化。
     *
     * @param string|null $path 项目目录
     */
    private function __construct(private readonly ?string $path)
    {
        $this->services = Services::make();
    }

    /**
     * 启动应用并返回内核。
     *
     * 首次调用会扫描项目目录、加载函数、执行加载钩子、构建内核，再执行启动与就绪钩子；
     * 重复调用返回同一内核。
     *
     * @return Kernel 内核
     */
    public function boot(): Kernel
    {
        if (null !== $this->kernel) {
            return $this->kernel;
        }

        $this->scan();
        $this->loadFunctions();
        $this->runHooks(self::PHASE_LOAD);

        $builder = KernelBuilder::make();
        foreach ($this->configDirectories as $directory) {
            $builder = $builder->withConfigDir($directory);
        }
        foreach ($this->languageDirectories as [$directory, $locale]) {
            $builder = $builder->withLanguageDir($directory, $locale);
        }

        $kernel = $builder->build();
        $this->kernel = $kernel;

        try {
            $this->runHooks(self::PHASE_START);
            $this->runStoreFiles($kernel);
            $this->runHooks(self::PHASE_READY);
        } catch (Throwable $exception) {
            // 启动失败时清空内核，允许修正后重新 boot
            $this->kernel = null;

            throw $exception;
        }

        return $kernel;
    }

    /**
     * 返回已构建的内核。
     *
     * @return Kernel|null 内核，未启动时为 null
     */
    public function kernel(): ?Kernel
    {
        return $this->kernel;
    }

    /**
     * 创建启动器。
     *
     * @param string|null $path 项目目录，目录不存在时立即抛异常
     * @return self 启动器
     */
    public static function make(?string $path = null): self
    {
        if (null !== $path && !is_dir($path)) {
            throw new InvalidArgumentException('应用目录不存在：' . $path);
        }

        return new self(null === $path ? null : rtrim($path, '/\\'));
    }

    /**
     * 返回项目目录。
     *
     * @return string|null 目录，未指定时为 null
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * 返回服务注册表。
     *
     * 启动前即可登记服务；启动过程中生成的实例同样保存在这里。
     *
     * @return Services 服务注册表
     */
    public function services(): Services
    {
        return $this->services;
    }

    /**
     * 追加配置目录。
     *
     * @param string $directory 配置目录
     * @return self 启动器
     */
    public function withConfigDir(string $directory): self
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('配置目录不存在：' . $directory);
        }

        $this->configDirectories[] = $directory;

        return $this;
    }

    /**
     * 登记服务工厂。
     *
     * @param string $id 服务名
     * @param callable(Services): object $factory 工厂
     * @return self 启动器
     */
    public function withFactory(string $id, callable $factory): self
    {
        $this->services->factory($id, $factory);

        return $this;
    }

    /**
     * 追加函数文件。
     *
     * @param string ...$files 函数文件
     * @return self 启动器
     */
    public function withFunctions(string ...$files): self
    {
        foreach ($files as $file) {
            if (!is_file($file)) {
                throw new InvalidArgumentException('函数文件不存在：' . $file);
            }
            $this->functionFiles[] = $file;
        }

        return $this;
    }

    /**
     * 登记阶段钩子。
     *
     * 钩子统一接收启动器；加载阶段的 `kernel()` 为 null，启动与就绪阶段已可取内核。
     *
     * @param string $phase 阶段
     * @param callable(self): void $hook 钩子
     * @return self 启动器
     */
    public function withHook(string $phase, callable $hook): self
    {
        if (!in_array($phase, self::PHASES, true)) {
            throw new InvalidArgumentException('未知的生命周期阶段：' . $phase);
        }

        $this->hooks[$phase][] = $hook;

        return $this;
    }

    /**
     * 追加项目语言目录。
     *
     * @param string $directory 语言目录
     * @param string $locale 目录对应的语言
     * @return self 启动器
     */
    public function withLanguageDir(string $directory, string $locale): self
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('语言目录不存在：' . $directory);
        }

        $this->languageDirectories[] = [$directory, $locale];

        return $this;
    }

    /**
     * 注册模块。
     *
     * @param Module $module 模块
     * @return self 注册后的启动器
     */
    public function withModule(Module $module): self
    {
        return $module->register($this);
    }

    /**
     * 登记服务实例。
     *
     * @param string $id 服务名
     * @param object $service 服务实例
     * @return self 启动器
     */
    public function withService(string $id, object $service): self
    {
        $this->services->set($id, $service);

        return $this;
    }

    /**
     * 加载函数文件。
     *
     * @return void
     */
    private function loadFunctions(): void
    {
        foreach ($this->functionFiles as $file) {
            require $file;
        }
    }

    /**
     * 执行阶段钩子。
     *
     * @param string $phase 阶段
     * @return void
     */
    private function runHooks(string $phase): void
    {
        foreach ($this->hooks[$phase] ?? [] as $hook) {
            $hook($this);
        }
    }

    /**
     * 执行启动脚本。
     *
     * 脚本内可使用 `$bootstrap`、`$kernel`、`$services`；返回可调用对象时再以同样三者调用。
     *
     * @param Kernel $kernel 内核
     * @return void
     */
    private function runStoreFiles(Kernel $kernel): void
    {
        $bootstrap = $this;
        $services = $this->services;

        foreach ($this->storeFiles as $file) {
            $hook = (static function (string $__file) use ($bootstrap, $kernel, $services): mixed {
                return require $__file;
            })($file);

            if (is_callable($hook)) {
                $hook($bootstrap, $kernel, $services);
            }
        }
    }

    /**
     * 扫描项目目录。
     *
     * `config` 作为配置目录，`language/*` 每子目录一种语言，`function/*.php` 与 `store/*.php`
     * 按文件名排序加载。只扫描一次。
     *
     * @return void
     */
    private function scan(): void
    {
        if ($this->scanned || null === $this->path) {
            return;
        }

        $this->scanned = true;

        if (is_dir($this->path . '/config')) {
            $this->configDirectories[] = $this->path . '/config';
        }

        $languages = glob($this->path . '/language/*', GLOB_ONLYDIR);
        if (false !== $languages) {
            sort($languages);
            foreach ($languages as $directory) {
                $this->languageDirectories[] = [$directory, basename($directory)];
            }
        }

        $this->functionFiles = array_values(array_unique([...$this->functionFiles, ...$this->files($this->path . '/function')]));
        $this->storeFiles = array_values(array_unique([...$this->storeFiles, ...$this->files($this->path . '/store')]));
    }

    /**
     * 列出目录下按名称排序的 PHP 文件。
     *
     * @param string $directory 目录
     * @return list<string> 文件路径
     */
    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = glob($directory . '/*.php');
        if (false === $files) {
            return [];
        }

        sort($files);

        return $files;
    }
}
