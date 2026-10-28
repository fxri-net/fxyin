<?php

declare(strict_types=1);

namespace Fxri\Translation;

use Fxri\Param\Merger;
use Fxri\Translation\Loader\PhpFileLoader;

/**
 * 语言目录。
 *
 * 以「语言 / 域 / 词条」三级组织词条，保留嵌套数组；写入操作返回新实例，实例本身不可变。
 */
final class Catalog
{
    /** 默认语言 */
    public const LOCALE = 'zh-cn';

    /** 默认域 */
    public const DOMAIN = 'messages';

    /**
     * 初始化。
     *
     * @param array<string, array<string, array<array-key, mixed>>> $messages 词条
     */
    private function __construct(private readonly array $messages)
    {
    }

    /**
     * 返回指定语言的全部词条。
     *
     * 多域按登记顺序递归合并，与 1.6.1 的扁平语言空间保持一致。
     *
     * @param string $locale 语言
     * @return array<array-key, mixed> 词条
     */
    public function all(string $locale = self::LOCALE): array
    {
        /** @var array<array-key, mixed> $all */
        $all = [];

        foreach ($this->messages[$locale] ?? [] as $domain) {
            $all = Merger::merge($all, $domain);
        }

        return $all;
    }

    /**
     * 返回指定语言的域列表。
     *
     * @param string $locale 语言
     * @return list<string> 域名
     */
    public function domains(string $locale = self::LOCALE): array
    {
        return array_keys($this->messages[$locale] ?? []);
    }

    /**
     * 从单个语言目录创建实例。
     *
     * @param string $directory 语言包目录
     * @param string $locale 目录对应的语言
     * @param string $domain 词条归入的域
     * @return self 语言目录
     */
    public static function fromDirectory(string $directory, string $locale = self::LOCALE, string $domain = self::DOMAIN): self
    {
        return self::make()->loadDirectory($directory, $locale, $domain);
    }

    /**
     * 从「语言包根目录」创建实例。
     *
     * 根目录下每个子目录是一种语言，子目录内的 PHP 文件按文件名顺序合并到默认域。
     *
     * @param string $root 语言包根目录
     * @return self 语言目录
     */
    public static function fromRoot(string $root): self
    {
        $directories = glob(rtrim($root, '/\\') . '/*', GLOB_ONLYDIR);
        if (false === $directories) {
            return self::make();
        }

        sort($directories);

        $messages = [];
        foreach ($directories as $directory) {
            $messages[basename($directory)] = [self::DOMAIN => PhpFileLoader::loadRawDirectory($directory)];
        }

        return new self($messages);
    }

    /**
     * 读取词条。
     *
     * 值可能为嵌套数组（如语言列表），未命中时返回默认值。
     *
     * @param string $name 词条
     * @param string $locale 语言
     * @param string|null $domain 域，缺省为默认域
     * @param mixed $default 缺失时的默认值
     * @return mixed 词条值或默认值
     */
    public function get(string $name, string $locale = self::LOCALE, ?string $domain = null, mixed $default = null): mixed
    {
        return $this->messages[$locale][$domain ?? self::DOMAIN][$name] ?? $default;
    }

    /**
     * 判断词条是否存在。
     *
     * 与 1.6.1 一致，值为 null 视为不存在。
     *
     * @param string $name 词条
     * @param string $locale 语言
     * @param string|null $domain 域，缺省为默认域
     * @return bool 是否存在
     */
    public function has(string $name, string $locale = self::LOCALE, ?string $domain = null): bool
    {
        return isset($this->messages[$locale][$domain ?? self::DOMAIN][$name]);
    }

    /**
     * 加载语言包文件。
     *
     * 单个或多个文件按传入顺序合并，域缺省为文件名；文件缺失时按空词条处理。
     *
     * @param string|array<array-key, string> $files 文件路径
     * @param string $locale 词条归入的语言
     * @param string|null $domain 词条归入的域
     * @return self 新语言目录
     */
    public function load(string|array $files, string $locale = self::LOCALE, ?string $domain = null): self
    {
        $messages = $this->messages;

        foreach ((array) $files as $file) {
            if ('' === $file) {
                continue;
            }

            $target = $domain ?? pathinfo($file, PATHINFO_FILENAME);
            $messages[$locale][$target] = Merger::merge(
                $messages[$locale][$target] ?? [],
                PhpFileLoader::loadRaw($file),
            );
        }

        return new self($messages);
    }

    /**
     * 加载整个语言包目录。
     *
     * 目录内文件按文件名排序合并，同名嵌套数组递归叠加。
     *
     * @param string $directory 语言包目录
     * @param string $locale 词条归入的语言
     * @param string $domain 词条归入的域
     * @return self 新语言目录
     */
    public function loadDirectory(string $directory, string $locale = self::LOCALE, string $domain = self::DOMAIN): self
    {
        $messages = $this->messages;
        $messages[$locale][$domain] = Merger::merge(
            $messages[$locale][$domain] ?? [],
            PhpFileLoader::loadRawDirectory($directory),
        );

        return new self($messages);
    }

    /**
     * 返回已登记的语言列表。
     *
     * @return list<string> 语言标识
     */
    public function locales(): array
    {
        return array_keys($this->messages);
    }

    /**
     * 创建实例。
     *
     * @param array<string, array<string, array<array-key, mixed>>> $messages 词条
     * @return self 语言目录
     */
    public static function make(array $messages = []): self
    {
        return new self($messages);
    }

    /**
     * 写入词条。
     *
     * 传入数组时批量合并，传入名称时写入单个词条；返回新实例，原实例不变。
     *
     * @param string|array<array-key, mixed> $name 词条或词条集合
     * @param mixed $value 词条值，批量写入时忽略
     * @param string $locale 语言
     * @param string $domain 域
     * @return self 新语言目录
     */
    public function set(string|array $name, mixed $value = null, string $locale = self::LOCALE, string $domain = self::DOMAIN): self
    {
        $messages = $this->messages;
        $current = $messages[$locale][$domain] ?? [];
        $messages[$locale][$domain] = is_array($name)
            ? Merger::merge($current, $name)
            : array_replace($current, [$name => $value]);

        return new self($messages);
    }
}
