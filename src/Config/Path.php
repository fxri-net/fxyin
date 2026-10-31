<?php

declare(strict_types=1);

namespace Fxri\Config;

/**
 * 配置键路径。
 *
 * 路径以点号分段，段内的 `/_` 还原为键名中的点；`Config`、`Store` 与命令行入参共用本类，
 * 保证读取与嵌套构造走同一套规则。
 *
 * @internal 实现细节，不对外承诺
 */
final class Path
{
    /**
     * 按点号路径构造嵌套数组。
     *
     * @param string $name 点号分隔的路径
     * @param mixed $value 叶子值
     * @return array<array-key, mixed> 嵌套数组
     */
    public static function nest(string $name, mixed $value): array
    {
        /** @var array<array-key, mixed> $nested 逐段包裹后的嵌套数组 */
        $nested = $value;
        foreach (array_reverse(explode('.', $name)) as $segment) {
            $nested = [str_replace('/_', '.', $segment) => $nested];
        }

        return $nested;
    }

    /**
     * 按点号路径读取。
     *
     * 值为 null 时仍算命中，由调用方决定兜底语义。
     *
     * @param array<array-key, mixed> $data 数据源
     * @param string $name 点号分隔的路径
     * @return array{found: bool, value: mixed} 命中标记与取到的值
     */
    public static function read(array $data, string $name): array
    {
        $value = $data;
        foreach (explode('.', $name) as $segment) {
            $segment = str_replace('/_', '.', $segment);
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return ['found' => false, 'value' => null];
            }

            $value = $value[$segment];
        }

        return ['found' => true, 'value' => $value];
    }
}
