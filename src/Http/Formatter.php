<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * 响应格式化器。
 *
 * 只描述「信封如何变成响应体」，替换格式只需换实现，不需要改调用方。
 */
interface Formatter
{
    /**
     * 取响应内容类型。
     *
     * @return string 内容类型
     */
    public function contentType(): string;

    /**
     * 格式化响应信封。
     *
     * @param Envelope $envelope 响应信封
     * @return string 响应体
     */
    public function format(Envelope $envelope): string;
}
