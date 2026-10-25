<?php

declare(strict_types=1);

namespace Fxri\Translation\Loader;

/**
 * PHP 语言包加载器。
 *
 * 语言包文件直接返回「词条 => 译文」数组，非字符串值一律丢弃。
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
        $data = is_file($file) ? require $file : [];

        return is_array($data) ? self::normalize($data) : [];
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
