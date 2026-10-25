<?php

declare(strict_types=1);

namespace Fxri\Http;

use InvalidArgumentException;

/**
 * 响应信封。
 *
 * 固定 ok、code、message、data 四个字段，字段含义由类本身决定，
 * 不再依赖配置里的位置数组。
 */
final class Envelope
{
    /** 成功码 */
    public const OK = 'ok';

    /**
     * 初始化。
     *
     * @param bool $ok 是否成功
     * @param string $code 业务码
     * @param string $message 提示语
     * @param mixed $data 业务数据
     */
    private function __construct(
        private readonly bool $ok,
        private readonly string $code,
        private readonly string $message,
        private readonly mixed $data,
    ) {
        // 空串会让调用方无法区分成功与失败，构造期直接拒绝
        if ('' === $code) {
            throw new InvalidArgumentException('响应码不能为空串');
        }
    }

    /**
     * 取业务码。
     *
     * @return string 业务码
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * 取业务数据。
     *
     * @return mixed 业务数据
     */
    public function data(): mixed
    {
        return $this->data;
    }

    /**
     * 构造失败响应。
     *
     * @param string $code 业务码
     * @param string $message 提示语
     * @param mixed $data 业务数据
     * @return self 响应信封
     */
    public static function failure(string $code, string $message = '', mixed $data = null): self
    {
        return new self(false, $code, $message, $data);
    }

    /**
     * 判断是否成功。
     *
     * @return bool 是否成功
     */
    public function isOk(): bool
    {
        return $this->ok;
    }

    /**
     * 创建实例。
     *
     * @param bool $ok 是否成功
     * @param string $code 业务码
     * @param mixed $data 业务数据
     * @param string $message 提示语
     * @return self 响应信封
     */
    public static function make(bool $ok, string $code, mixed $data = null, string $message = ''): self
    {
        return new self($ok, $code, $message, $data);
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
     * @param mixed $data 业务数据
     * @param string $message 提示语
     * @param string $code 业务码
     * @return self 响应信封
     */
    public static function success(mixed $data = null, string $message = '', string $code = self::OK): self
    {
        return new self(true, $code, $message, $data);
    }

    /**
     * 转为数组。
     *
     * @return array{ok: bool, code: string, message: string, data: mixed} 字段固定的数组
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
