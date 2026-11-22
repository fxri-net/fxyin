<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Data\Page;

/**
 * 响应信封。
 *
 * 对外固定五个字段：code、message、data、extend、debug 的响应信封。
 * success 只用于内部判断成败，不参与输出；code 为整数业务码。
 *
 * @template T 业务数据类型
 */
final class Envelope
{
    /** 成功码 */
    public const SUCCESS = 200;

    /**
     * 初始化。
     *
     * @param bool $success 是否成功
     * @param int $code 业务码
     * @param string $message 提示语
     * @param mixed $data 业务数据
     * @param array<array-key, mixed> $extend 扩展参数
     * @param array<array-key, mixed> $debug 调试信息
     */
    private function __construct(
        private readonly bool $success,
        private readonly int $code,
        private readonly string $message,
        /** @var T 业务数据 */
        private readonly mixed $data,
        private readonly array $extend,
        private readonly array $debug,
    ) {
    }

    /**
     * 取业务码。
     *
     * @return int 业务码
     */
    public function code(): int
    {
        return $this->code;
    }

    /**
     * 取业务数据。
     *
     * @return T 业务数据
     */
    public function data(): mixed
    {
        return $this->data;
    }

    /**
     * 取调试信息。
     *
     * @return array<array-key, mixed> 调试信息
     */
    public function debug(): array
    {
        return $this->debug;
    }

    /**
     * 取扩展参数。
     *
     * @return array<array-key, mixed> 扩展参数
     */
    public function extend(): array
    {
        return $this->extend;
    }

    /**
     * 构造失败响应。
     *
     * @template TData
     * @param int $code 业务码
     * @param string $message 提示语
     * @param TData $data 业务数据
     * @param array<array-key, mixed> $extend 扩展参数
     * @param array<array-key, mixed> $debug 调试信息
     * @return self<TData> 响应信封
     */
    public static function failure(int $code, string $message = '', mixed $data = null, array $extend = [], array $debug = []): self
    {
        return new self(false, $code, $message, $data, $extend, $debug);
    }

    /**
     * 判断是否成功。
     *
     * @return bool 是否成功
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * 扩展位归一为具名映射。
     *
     * 空数组输出空对象、关联数组原样输出、非空列表归入保留键 `items`，
     * 保证 JSON 侧恒为对象，不随空 / 非空在对象与数组之间翻转。
     *
     * @param array<array-key, mixed> $value 扩展位取值
     * @return object 具名映射
     */
    private static function map(array $value): object
    {
        if ([] === $value) {
            return new \stdClass();
        }
        if (array_is_list($value)) {
            // array_is_list 已保证键为连续 0..n-1，无需再 array_values 重排
            return (object) ['items' => $value];
        }

        return (object) $value;
    }

    /**
     * 取提示语。
     *
     * @return string 提示语
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * 构造成功响应。
     *
     * @template TData
     * @param TData $data 业务数据
     * @param string $message 提示语
     * @param int $code 业务码
     * @param array<array-key, mixed> $extend 扩展参数
     * @param array<array-key, mixed> $debug 调试信息
     * @return self<TData> 响应信封
     */
    public static function success(mixed $data = null, string $message = '', int $code = self::SUCCESS, array $extend = [], array $debug = []): self
    {
        return new self(true, $code, $message, $data, $extend, $debug);
    }

    /**
     * 转为数组。
     *
     * @return array{code: int, message: string, data: mixed, extend: object, debug: object} 字段固定的数组
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data instanceof Page ? $this->data->toArray() : $this->data,
            // 两个扩展位统一走具名映射，空值输出 {}、列表归入 items
            'extend' => self::map($this->extend),
            'debug' => self::map($this->debug),
        ];
    }
}
