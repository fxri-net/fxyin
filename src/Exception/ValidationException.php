<?php

declare(strict_types=1);

namespace Fxri\Exception;

use InvalidArgumentException;

/**
 * 数据校验异常。
 *
 * 入参缺失或格式不符时抛出，消息保持中文，敏感入参不得进入消息。
 */
final class ValidationException extends InvalidArgumentException
{
}
