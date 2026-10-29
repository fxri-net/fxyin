<?php

declare(strict_types=1);

namespace Fxri\Config;

use Fxri\Param\Merger;
use RuntimeException;

/**
 * 配置加载器。
 *
 * 按目录递归读取 PHP 配置文件，相对路径去掉扩展名后按分隔符转嵌套键；
 * 多目录按登记顺序叠加，后者覆盖前者。
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
            foreach ($this->paths($directory) as $item) {
                $data = Merger::merge($data, $this->nest($item['path'], $this->read($item['file'])));
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
     * 列出目录下的配置项。
     *
     * 同一层先文件后子目录，各自按名称排序；子目录递归展开，目录名并入键路径。
     *
     * @param string $directory 配置目录
     * @param string $prefix 上一级累积的键路径
     * @return list<array{path: string, file: string}> 键路径与文件路径
     */
    private function paths(string $directory, string $prefix = ''): array
    {
        $directory = rtrim($directory, '/\\');
        $paths = [];

        $files = glob($directory . '/*.php') ?: [];
        sort($files);
        foreach ($files as $file) {
            $paths[] = ['path' => $prefix . pathinfo($file, PATHINFO_FILENAME), 'file' => $file];
        }

        $directories = glob($directory . '/*', GLOB_ONLYDIR) ?: [];
        sort($directories);
        foreach ($directories as $child) {
            $paths = [...$paths, ...$this->paths($child, $prefix . basename($child) . '.')];
        }

        return $paths;
    }

    /**
     * 把键路径展开为嵌套数组。
     *
     * 分隔符既包括目录层级，也包括文件名里的点号，因此 `third.oss` 与 `third/oss` 等价。
     *
     * @param string $path 点号分隔的键路径
     * @param array<array-key, mixed> $value 文件配置
     * @return array<array-key, mixed> 嵌套数组
     */
    private function nest(string $path, array $value): array
    {
        $data = $value;
        foreach (array_reverse(explode('.', $path)) as $segment) {
            $data = [$segment => $data];
        }

        return $data;
    }

    /**
     * 读取单个配置文件。
     *
     * 只接受返回数组的配置文件，其余返回值视为结构错误并抛出异常。
     *
     * @param string $file 文件路径
     * @return array<array-key, mixed> 文件返回的配置
     */
    private function read(string $file): array
    {
        $data = require $file;

        if (!is_array($data)) {
            throw new RuntimeException('配置文件必须返回数组：' . $file);
        }

        return $data;
    }
}
