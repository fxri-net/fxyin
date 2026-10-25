<?php

declare(strict_types=1);

namespace Fxri\Param;

use InvalidArgumentException;
use JsonException;

/**
 * JSON 编解码。
 *
 * 统一抛异常，调用方不必再判断 false；转数组接口对非法输入返回 null 而不抛。
 */
final class Json
{
    /**
     * 解码为 PHP 值。
     *
     * @param string $value 待解码内容
     * @return mixed 解码结果
     */
    public static function decode(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('JSON 解析失败：' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * 编码为 JSON 串。
     *
     * @param mixed $value 待编码值
     * @param int $flags 额外编码选项
     * @return string 编码结果
     */
    public static function encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        try {
            return json_encode($value, $flags | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('JSON 编码失败：' . $exception->getMessage(), 0, $exception);
        }
    }

    /**
     * 尝试解码为数组，非法输入返回 null。
     *
     * @param mixed $value 待解码值
     * @return array<array-key, mixed>|null 解码结果
     */
    public static function toArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
