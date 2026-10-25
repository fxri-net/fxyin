<?php

declare(strict_types=1);

namespace Fxri\Data;

use Fxri\Exception\SameDataException;
use Fxri\Exception\ValidationException;

/**
 * 数据差异。
 *
 * 逐键严格比较新旧数据，相同即抛异常，让调用方按异常分支处理，不再返回响应信封。
 */
final class Diff
{
    /**
     * 初始化。
     *
     * @param array<array-key, mixed> $changes 变化键的新值
     * @param array<int, array-key> $keys 变化键名，含被删除的键
     */
    private function __construct(
        private readonly array $changes,
        private readonly array $keys,
    ) {
    }

    /**
     * 取变化键的新值。
     *
     * @return array<array-key, mixed> 变化子集
     */
    public function changes(): array
    {
        return $this->changes;
    }

    /**
     * 取变化键名。
     *
     * @return array<int, array-key> 键名列表
     */
    public function keys(): array
    {
        return $this->keys;
    }

    /**
     * 创建实例。
     *
     * @param mixed $new 新数据
     * @param mixed $old 旧数据
     * @return self 差异
     */
    public static function make(mixed $new, mixed $old): self
    {
        if (null === $new) {
            throw new ValidationException('缺少新数据');
        }
        if (null === $old) {
            throw new ValidationException('缺少旧数据');
        }
        if (!is_array($new) || !is_array($old)) {
            throw new ValidationException('数据格式错误');
        }

        $changes = [];
        foreach ($new as $key => $value) {
            // 严格比较，避免 '1' 与 1 被当成相同
            if (array_key_exists($key, $old) && $old[$key] === $value) {
                continue;
            }
            $changes[$key] = $value;
        }

        $keys = array_keys($changes);
        foreach (array_keys($old) as $key) {
            if (!array_key_exists($key, $new)) {
                $keys[] = $key;
            }
        }

        if ([] === $keys) {
            throw new SameDataException('数据相同');
        }

        return new self($changes, $keys);
    }
}
