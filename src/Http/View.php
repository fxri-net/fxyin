<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\File\Path;
use InvalidArgumentException;

/**
 * 视图。
 *
 * 负责模板路径解析与安全校验，渲染动作交给注入的渲染器。
 */
final class View
{
    /**
     * 初始化。
     *
     * @param string $directory 模板目录
     * @param Renderer $renderer 渲染器
     */
    private function __construct(
        private readonly string $directory,
        private readonly Renderer $renderer,
    ) {
    }

    /**
     * 取模板目录。
     *
     * @return string 模板目录
     */
    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * 创建实例。
     *
     * @param string $directory 模板目录
     * @param Renderer $renderer 渲染器
     * @return self 视图
     */
    public static function make(string $directory, Renderer $renderer): self
    {
        return new self($directory, $renderer);
    }

    /**
     * 渲染模板。
     *
     * @param string $template 相对模板目录的模板名
     * @param array<array-key, mixed> $data 模板数据
     * @return string 渲染结果
     */
    public function render(string $template, array $data = []): string
    {
        return $this->renderer->render($this->path($template), $data);
    }

    /**
     * 替换模板目录。
     *
     * @param string $directory 模板目录
     * @return self 新的视图
     */
    public function withDirectory(string $directory): self
    {
        return new self($directory, $this->renderer);
    }

    /**
     * 解析模板路径。
     *
     * 先拒绝上级目录写法，再做拼接，避免归一化把越界路径抹平。
     *
     * @param string $template 模板名
     * @return string 模板路径
     */
    private function path(string $template): string
    {
        $name = trim($template);
        if ('' === $name) {
            throw new InvalidArgumentException('模板名不能为空串');
        }
        if (str_contains($name, '..')) {
            throw new InvalidArgumentException('模板名不能包含上级目录：' . $name);
        }

        $path = Path::join($this->directory, $name);
        if (!is_file($path)) {
            throw new InvalidArgumentException('模板不存在：' . $path);
        }

        return $path;
    }
}
