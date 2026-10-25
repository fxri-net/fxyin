<?php

declare(strict_types=1);

namespace Fxri\File;

/**
 * 媒体类型映射。
 *
 * 用内置映射表代替扩展探测，既不依赖 fileinfo，也便于补齐业务侧需要的类型。
 */
final class MediaInfo
{
    /** 媒体类型与扩展名映射 */
    private const MAP = [
        'application/javascript' => 'js',
        'application/json' => 'json',
        'application/msword' => 'doc',
        'application/pdf' => 'pdf',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/xml' => 'xml',
        'application/zip' => 'zip',
        'audio/mpeg' => 'mp3',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/vnd.microsoft.icon' => 'ico',
        'image/webp' => 'webp',
        'text/css' => 'css',
        'text/csv' => 'csv',
        'text/html' => 'html',
        'text/plain' => 'txt',
        'video/mp4' => 'mp4',
    ];

    /**
     * 由媒体类型取扩展名。
     *
     * @param string $mime 媒体类型，可带 charset 等参数
     * @return string|null 扩展名，未收录时返回 null
     */
    public static function extension(string $mime): ?string
    {
        $parts = explode(';', $mime);
        $mime = strtolower(trim($parts[0]));

        return self::MAP[$mime] ?? null;
    }

    /**
     * 由扩展名取媒体类型。
     *
     * @param string $extension 扩展名，可带前导点
     * @return string|null 媒体类型，未收录时返回 null
     */
    public static function mime(string $extension): ?string
    {
        $extension = strtolower(ltrim($extension, '.'));
        $mime = array_search($extension, self::MAP, true);

        return is_string($mime) ? $mime : null;
    }
}
