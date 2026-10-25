<?php

declare(strict_types=1);

namespace Fxri\Config;

use Fxri\Param\Merger;

/**
 * 配置加载器。
 *
 * 按目录读取 PHP 配置文件，文件名即顶层键；多目录按登记顺序叠加，后者覆盖前者。
 */
final class ConfigLoader
{
    /**
     * 初始化。
     *
     * @param list<string> $directories 配置目录
     */
    private function __construct(private readonly array $directories)
    {
    }

    /**
     * 加载全部目录并生成配置。
     *
     * @return Config 配置实例
     */
    public function load(): Config
    {
        $data = [];

        foreach ($this->directories as $directory) {
            foreach ($this->files($directory) as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                $data = Merger::merge($data, [$name => $this->read($file)]);
            }
        }

        return Config::make($data);
    }

    /**
     * 创建实例。
     *
     * @param string ...$directories 配置目录
     * @return self 加载器
     */
    public static function make(string ...$directories): self
    {
        return new self(array_values($directories));
    }

    /**
     * 追加配置目录。
     *
     * @param string $directory 配置目录
     * @return self 新实例
     */
    public function withDirectory(string $directory): self
    {
        return new self([...$this->directories, $directory]);
    }

    /**
     * 列出目录下按名称排序的 PHP 文件。
     *
     * @param string $directory 配置目录
     * @return list<string> 文件路径
     */
    private function files(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . '/*.php');
        if (false === $files) {
            return [];
        }

        sort($files);

        return $files;
    }

    /**
     * 读取单个配置文件。
     *
     * 文件未返回数组时视为无配置，避免一个坏文件影响整体加载。
     *
     * @param string $file 文件路径
     * @return array<array-key, mixed> 文件返回的配置
     */
    private function read(string $file): array
    {
        $data = require $file;

        return is_array($data) ? $data : [];
    }
}
