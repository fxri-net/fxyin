<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 定形流水线。
 *
 * 多个定义按加入顺序依次作用于同一份输入，前一环的结果作为后一环的输入。
 */
final class Pipeline
{
    /**
     * 私有构造，统一由静态入口创建。
     *
     * @param list<Definition> $definitions 定形定义序列
     */
    private function __construct(private readonly array $definitions)
    {
    }

    /**
     * 追加一个定义。
     *
     * @param Definition $definition 定形定义
     * @return self 新流水线
     */
    public function add(Definition $definition): self
    {
        return new self([...$this->definitions, $definition]);
    }

    /**
     * 创建流水线。
     *
     * @param Definition ...$definitions 定形定义
     * @return self 流水线
     */
    public static function make(Definition ...$definitions): self
    {
        return new self(array_values($definitions));
    }

    /**
     * 依次对输入定形。
     *
     * @param array<array-key, mixed> $input 原始输入
     * @return array<array-key, mixed> 定形结果
     */
    public function resolve(array $input): array
    {
        $result = $input;
        foreach ($this->definitions as $definition) {
            $result = $definition->resolve($result);
        }

        return $result;
    }
}
