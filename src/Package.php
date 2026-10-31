<?php

declare(strict_types=1);

namespace Fxri;

/**
 * 包元信息。
 *
 * 仅承载框架自身的标识与版本，供日志、诊断输出与接入方版本校验引用。
 */
final class Package
{
    /** 包名 */
    public const NAME = 'fxri/fxyin';

    /** 当前版本号 */
    public const VERSION = '2.2.2';

    /**
     * 返回包标识与版本号。
     *
     * @return string 形如「包名 版本号」
     */
    public static function describe(): string
    {
        return self::NAME . ' ' . self::VERSION;
    }
}
