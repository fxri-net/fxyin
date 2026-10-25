<?php

declare(strict_types=1);

namespace Fxri\Text;

/**
 * 时间粒度。
 *
 * 枚举值本身就是输出格式，避免调用方再传一份格式串。
 */
enum DateGranularity: string
{
    /** 仅日期 */
    case Date = 'Y-m-d';
    /** 日期与时间 */
    case DateTime = 'Y-m-d H:i:s';
    /** 仅时间 */
    case Time = 'H:i:s';

    /**
     * 取输出格式。
     *
     * @return string 格式串
     */
    public function format(): string
    {
        return $this->value;
    }
}
