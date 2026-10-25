<?php

declare(strict_types=1);

namespace Fxri\Exception;

use RuntimeException;

/**
 * 数据相同异常。
 *
 * 差异比较没有发现变化时抛出，调用方据此走「无变更」分支。
 */
final class SameDataException extends RuntimeException
{
}
