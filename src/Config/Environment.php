<?php

declare(strict_types=1);

namespace Fxri\Config;

/**
 * 环境变量集合。
 *
 * 只负责把 `.env` 文本解析成键值对，不读写进程环境，便于测试与复用。
 */
final class Environment
{
    /**
     * 初始化。
     *
     * @param array<string, string> $data 键值对
     */
    private function __construct(private readonly array $data)
    {
    }

    /**
     * 返回全部键值对。
     *
     * @return array<string, string> 键值对
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * 从文件解析。
     *
     * 文件不存在时返回空集合，不抛异常，便于可选加载。
     *
     * @param string $file 文件路径
     * @return self 环境变量集合
     */
    public static function fromFile(string $file): self
    {
        $contents = is_file($file) ? file_get_contents($file) : false;
        if (false === $contents) {
            return new self([]);
        }

        return self::fromString($contents);
    }

    /**
     * 从文本解析。
     *
     * 支持空行、整行注释、`export ` 前缀与成对引号；不处理行内注释。
     *
     * @param string $contents 文本内容
     * @return self 环境变量集合
     */
    public static function fromString(string $contents): self
    {
        $data = [];

        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if ('' === $line || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $position = strpos($line, '=');
            if (false === $position) {
                continue;
            }

            $key = trim(substr($line, 0, $position));
            if ('' === $key) {
                continue;
            }

            $data[$key] = self::unquote(trim(substr($line, $position + 1)));
        }

        return new self($data);
    }

    /**
     * 读取单个键。
     *
     * @param string $name 键名
     * @param mixed $default 缺失时的默认值
     * @return mixed 值
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    /**
     * 判断键是否存在。
     *
     * @param string $name 键名
     * @return bool 是否存在
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * 创建实例。
     *
     * @param array<string, string> $data 键值对
     * @return self 环境变量集合
     */
    public static function make(array $data = []): self
    {
        return new self($data);
    }

    /**
     * 合并另一份环境变量，同名键以后者为准。
     *
     * @param self $other 覆盖层
     * @return self 新实例
     */
    public function merge(self $other): self
    {
        return new self(array_replace($this->data, $other->data));
    }

    /**
     * 去掉值两侧成对的单引号或双引号。
     *
     * @param string $value 原始值
     * @return string 去引号后的值
     */
    private static function unquote(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (('"' === $first && '"' === $last) || ("'" === $first && "'" === $last)) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
