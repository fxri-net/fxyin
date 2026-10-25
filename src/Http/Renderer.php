<?php

declare(strict_types=1);

namespace Fxri\Http;

/**
 * 视图渲染器。
 *
 * 只约定「模板路径 + 数据 => 字符串」，模板引擎由接入方提供。
 */
interface Renderer
{
    /**
     * 渲染模板。
     *
     * @param string $template 模板文件路径
     * @param array<array-key, mixed> $data 模板数据
     * @return string 渲染结果
     */
    public function render(string $template, array $data): string;
}
