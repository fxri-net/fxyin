<?php

declare(strict_types=1);

namespace Fxri\Data;

use Fxri\Support\Type;

/**
 * 空值判定策略。
 *
 * 取代旧 paramEmpty 的数字参数，把「空串算不算空」交给调用方声明。
 */
enum NullPolicy
{
    /** null 与空串都算空 */
    case Blank;
    /** 仅 null 算空 */
    case StrictNull;

    /**
     * 判断是否为空值。
     *
     * @param mixed $value 待判定值
     * @return bool 是否为空
     */
    public function isEmpty(mixed $value): bool
    {
        return match ($this) {
            self::Blank => Type::isBlank($value),
            self::StrictNull => null === $value,
        };
    }
}
