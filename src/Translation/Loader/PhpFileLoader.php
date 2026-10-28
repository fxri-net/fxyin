<?php

declare(strict_types=1);

namespace Fxri\Translation\Loader;

use Fxri\Param\Merger;

/**
 * PHP 语言包加载器。
 *
 * 语言包文件直接返回「词条 => 译文」数组；平面加载丢弃非字符串值，原始加载保留嵌套数组。
 */
final class PhpFileLoader
{
    /**
     * 加载单个语言包。
     *
     * 文件不存在时返回空数组，便于可选覆盖。
     *
     * @param string $file 文件路径
     * @return array<string, string> 词条对照
     */
    public static function load(string $file): array
    {
        return self::normalize(self::loadRaw($file));
    }

    /**
     * 加载目录下全部语言包。
     *
     * 按文件名排序后合并，同名词条以后者为准。
     *
     * @param string $directory 语言包目录
     * @return array<string, string> 词条对照
     */
    public static function loadDirectory(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . '/*.php');
        if (false === $files) {
            return [];
        }

        sort($files);

        $messages = [];
        foreach ($files as $file) {
            $messages = array_merge($messages, self::load($file));
        }

        return $messages;
    }

    /**
     * 原始加载单个语言包。
     *
     * 保留嵌套数组，供语言目录承载 `lang_list` 等结构化词条。
     *
     * @param string $file 文件路径
     * @return array<array-key, mixed> 原始词条
     */
    public static function loadRaw(string $file): array
    {
        $data = is_file($file) ? require $file : [];

        return is_array($data) ? $data : [];
    }

    /**
     * 原始加载目录下全部语言包。
     *
     * 按文件名排序后合并，同名词条以后者为准，嵌套数组递归合并。
     *
     * @param string $directory 语言包目录
     * @return array<array-key, mixed> 原始词条
     */
    public static function loadRawDirectory(string $directory): array
    {
        $files = glob(rtrim($directory, '/\\') . '/*.php');
        if (false === $files) {
            return [];
        }

        sort($files);

        $messages = [];
        foreach ($files as $file) {
            $messages = Merger::merge($messages, self::loadRaw($file));
        }

        return $messages;
    }

    /**
     * 只保留字符串值。
     *
     * @param array<array-key, mixed> $data 原始数据
     * @return array<string, string> 词条对照
     */
    private static function normalize(array $data): array
    {
        $messages = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $messages[(string) $key] = $value;
            }
        }

        return $messages;
    }
}
