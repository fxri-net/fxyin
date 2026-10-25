<?php

declare(strict_types=1);

namespace Fxri\Support;

/**
 * 随机串字符集。
 *
 * 只描述可用字符，不承载生成逻辑，避免调用方以数字选择字符范围。
 */
enum RandomCharset: string
{
    /** 纯数字 */
    case Numeric = '0123456789';

    /** 小写字母 */
    case Lower = 'abcdefghijklmnopqrstuvwxyz';

    /** 大写字母 */
    case Upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /** 数字与大小写字母 */
    case Alnum = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * 返回字符集内容。
     *
     * @return string 可直接按位取字符的字符集
     */
    public function characters(): string
    {
        return $this->value;
    }

    /**
     * 返回字符集长度。
     *
     * @return int 可用字符个数
     */
    public function length(): int
    {
        return strlen($this->value);
    }
}
