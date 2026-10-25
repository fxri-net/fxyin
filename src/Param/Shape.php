<?php

declare(strict_types=1);

namespace Fxri\Param;

/**
 * 参数定形规则。
 *
 * 用链式调用描述「取哪些键、缺失时填什么、取到后怎么转换」，
 * 替代旧实现里用点分数字串区分行为的 pattern 参数。
 */
final class Shape
{
    /**
     * 私有构造，统一由静态入口与链式方法创建。
     *
     * @param BaseMode $base 基底模式
     * @param FillMode $fill 填充模式
     * @param TriggerMode $trigger 触发条件
     * @param bool $defaultsFirst 默认值是否优先于输入
     * @param TransformMode|null $transform 取值后的转换方式
     */
    private function __construct(
        private readonly BaseMode $base,
        private readonly FillMode $fill,
        private readonly TriggerMode $trigger,
        private readonly bool $defaultsFirst,
        private readonly ?TransformMode $transform,
    ) {
    }

    /**
     * 按规则定形输入。
     *
     * @param array<array-key, mixed> $input 原始输入
     * @param array<array-key, mixed> $defaults 键与默认值
     * @return array<array-key, mixed> 定形结果
     */
    public function apply(array $input, array $defaults): array
    {
        $result = BaseMode::Merge === $this->base ? $input : [];

        foreach ($defaults as $key => $default) {
            $exists = isset($input[$key]);
            $blank = $exists && '' === $input[$key];
            // 触发条件只影响「是否采用默认值」，不改变「键已存在则保留输入」的优先级
            $triggered = TriggerMode::Blank === $this->trigger ? (!$exists || $blank) : !$exists;

            if ($this->defaultsFirst && null !== $default) {
                $result[$key] = $default;

                continue;
            }
            if ($triggered) {
                $result[$key] = FillMode::Null === $this->fill ? null : $default;

                continue;
            }

            $result[$key] = $input[$key];
        }

        if (null !== $this->transform) {
            foreach (array_keys($defaults) as $key) {
                $result[$key] = $this->transform->apply($result[$key] ?? null);
            }
        }

        return $result;
    }

    /**
     * 追加逗号分隔转换。
     *
     * @return self 新规则
     */
    public function csv(): self
    {
        return $this->with(transform: TransformMode::Csv);
    }

    /**
     * 让非 null 默认值优先于输入。
     *
     * @return self 新规则
     */
    public function defaultsFirst(): self
    {
        return $this->with(defaultsFirst: true);
    }

    /**
     * 仅在键不存在时填充。
     *
     * @return self 新规则
     */
    public function fillWhenAbsent(): self
    {
        return $this->with(trigger: TriggerMode::Absent);
    }

    /**
     * 键不存在或值为空串时填充。
     *
     * @return self 新规则
     */
    public function fillWhenBlank(): self
    {
        return $this->with(trigger: TriggerMode::Blank);
    }

    /**
     * 追加 JSON 转换。
     *
     * @return self 新规则
     */
    public function json(): self
    {
        return $this->with(transform: TransformMode::Json);
    }

    /**
     * 以输入为基底，保留未声明的键。
     *
     * @return self 新规则
     */
    public static function merge(): self
    {
        return new self(BaseMode::Merge, FillMode::Value, TriggerMode::Absent, false, null);
    }

    /**
     * 缺失时填 null 而不是默认值。
     *
     * @return self 新规则
     */
    public function nullWhenAbsent(): self
    {
        return $this->with(fill: FillMode::Null);
    }

    /**
     * 缺失或空串时填 null。
     *
     * @return self 新规则
     */
    public function nullWhenBlank(): self
    {
        return $this->with(fill: FillMode::Null, trigger: TriggerMode::Blank);
    }

    /**
     * 只保留声明的键。
     *
     * @return self 新规则
     */
    public static function only(): self
    {
        return new self(BaseMode::Only, FillMode::Value, TriggerMode::Absent, false, null);
    }

    /**
     * 基于当前规则派生新规则。
     *
     * @param BaseMode|null $base 基底模式
     * @param FillMode|null $fill 填充模式
     * @param TriggerMode|null $trigger 触发条件
     * @param bool|null $defaultsFirst 默认值是否优先
     * @param TransformMode|null $transform 转换方式
     * @return self 新规则
     */
    private function with(
        ?BaseMode $base = null,
        ?FillMode $fill = null,
        ?TriggerMode $trigger = null,
        ?bool $defaultsFirst = null,
        ?TransformMode $transform = null,
    ): self {
        return new self(
            $base ?? $this->base,
            $fill ?? $this->fill,
            $trigger ?? $this->trigger,
            $defaultsFirst ?? $this->defaultsFirst,
            $transform ?? $this->transform,
        );
    }
}
