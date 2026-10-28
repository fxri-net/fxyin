<?php

declare(strict_types=1);

namespace Fxri\Http;

use Fxri\Param\Json;
use Fxri\Param\Picker;
use Fxri\Text\Encode;

/**
 * 请求入参聚合。
 *
 * 把查询串、表单、JSON 请求体与命令行参数收敛成只读取值入口，承接 1.x 门面
 * `Client::param()` 的能力；来源由构造方显式传入，不直接读取超全局变量。
 */
final class RequestInput
{
    /**
     * 初始化。
     *
     * @param array<array-key, mixed> $query 查询串参数
     * @param array<array-key, mixed> $post 表单参数
     * @param string $body 原始请求体
     * @param array<array-key, mixed> $cli 命令行参数
     */
    private function __construct(
        private readonly array $query,
        private readonly array $post,
        private readonly string $body,
        private readonly array $cli,
    ) {
    }

    /**
     * 合并全部来源。
     *
     * 顺序为查询串、表单、JSON 请求体、命令行参数，同名键由后出现的来源覆盖；
     * 请求体不是 JSON 对象时不参与合并，避免把不可用的字符串混进数组。
     *
     * @return array<array-key, mixed> 合并结果
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json() ?? [], $this->cli);
    }

    /**
     * 取原始请求体。
     *
     * @return string 请求体
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * 取命令行参数。
     *
     * @return array<array-key, mixed> 命令行参数
     */
    public function cli(): array
    {
        return $this->cli;
    }

    /**
     * 从超全局变量构造。
     *
     * @param array<array-key, mixed> $cli 命令行参数
     * @return self 入参聚合
     */
    public static function fromGlobals(array $cli = []): self
    {
        $body = file_get_contents('php://input');

        return new self($_GET, $_POST, is_string($body) ? $body : '', $cli);
    }

    /**
     * 从请求上下文构造。
     *
     * 查询串按嵌套结构解码；表单需要调用方显式传入，PSR-7 请求本身不携带表单快照。
     *
     * @param RequestContext $request 请求上下文
     * @param array<array-key, mixed> $post 表单参数
     * @param array<array-key, mixed> $cli 命令行参数
     * @return self 入参聚合
     */
    public static function fromRequest(RequestContext $request, array $post = [], array $cli = []): self
    {
        return new self(Encode::decodeAll($request->queryString()), $post, $request->body(), $cli);
    }

    /**
     * 按合并结果取单个值。
     *
     * @param string $key 参数名
     * @param mixed $default 缺失时的默认值
     * @return mixed 参数值
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * 解析 JSON 请求体。
     *
     * @return array<array-key, mixed>|null 解析结果，非 JSON 对象时为 null
     */
    public function json(): ?array
    {
        return Json::toArray($this->body);
    }

    /**
     * 创建实例。
     *
     * @param array<array-key, mixed> $query 查询串参数
     * @param array<array-key, mixed> $post 表单参数
     * @param string $body 原始请求体
     * @param array<array-key, mixed> $cli 命令行参数
     * @return self 入参聚合
     */
    public static function make(array $query = [], array $post = [], string $body = '', array $cli = []): self
    {
        return new self($query, $post, $body, $cli);
    }

    /**
     * 按给定结构提取，缺失键补 null。
     *
     * @param array<array-key, mixed> $keys 提取结构，数组值表示递归下钻
     * @return array<array-key, mixed> 提取结果
     */
    public function only(array $keys): array
    {
        return Picker::pick($this->all(), $keys);
    }

    /**
     * 取表单参数。
     *
     * @return array<array-key, mixed> 表单参数
     */
    public function post(): array
    {
        return $this->post;
    }

    /**
     * 取查询串参数。
     *
     * @return array<array-key, mixed> 查询串参数
     */
    public function query(): array
    {
        return $this->query;
    }
}
