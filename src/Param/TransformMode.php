<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 取值后的转换方式。
 *
 * 转换只关心「串 → 数组」，无法转换的值统一得到空数组，便于调用方免去类型判断。
 */
enum TransformMode
{
    /** 逗号分隔串转数组 */
    case Csv;
    /** JSON 串转数组 */
    case Json;

    /**
     * 执行转换。
     *
     * @param mixed $value 待转换值
     * @return array<array-key, mixed> 转换结果
     */
    public function apply(mixed $value): array
    {
        return match ($this) {
            self::Csv => self::toList($value),
            self::Json => Json::toArray($value) ?? [],
        };
    }

    /**
     * 把逗号分隔串转成数组。
     *
     * @param mixed $value 待转换值
     * @return array<array-key, mixed> 转换结果
     */
    private static function toList(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) && !is_numeric($value)) {
            return [];
        }

        $items = explode(',', (string) $value);
        $items = array_map(static fn (string $item): string => trim($item), $items);

        return array_values(array_filter($items, static fn (string $item): bool => '' !== $item));
    }
}
