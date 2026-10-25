<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 参数定义。
 *
 * 把一个 Shape 规则与一组键默认值绑在一起，成为可复用的定形单元。
 */
final class Definition
{
    /**
     * 私有构造，统一由静态入口创建。
     *
     * @param array<array-key, mixed> $defaults 键与默认值
     * @param Shape $shape 定形规则
     */
    private function __construct(
        private readonly array $defaults,
        private readonly Shape $shape,
    ) {
    }

    /**
     * 创建定义。
     *
     * @param array<array-key, mixed> $defaults 键与默认值
     * @param Shape|null $shape 定形规则，缺省为保留未声明键
     * @return self 定义
     */
    public static function make(array $defaults, ?Shape $shape = null): self
    {
        return new self($defaults, $shape ?? Shape::merge());
    }

    /**
     * 对输入定形。
     *
     * @param array<array-key, mixed> $input 原始输入
     * @return array<array-key, mixed> 定形结果
     */
    public function resolve(array $input): array
    {
        return $this->shape->apply($input, $this->defaults);
    }
}
