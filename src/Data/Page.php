<?php

declare(strict_types=1);

namespace Fxri\Data;

/**
 * 分页数据壳。
 *
 * 分页接口的响应数据统一为 total、records、page、size 四个键，
 * 非分页接口不使用本类，直接把业务数据放进响应的 data。
 *
 * @template T 当页记录类型
 */
final class Page
{
    /**
     * 初始化。
     *
     * @param list<T> $records 当页记录
     * @param int $total 总条数
     * @param int|null $page 当前页码
     * @param int|null $size 每页条数
     */
    private function __construct(
        /** @var list<T> 当页记录 */
        private readonly array $records,
        private readonly int $total,
        private readonly ?int $page,
        private readonly ?int $size,
    ) {
    }

    /**
     * 创建实例。
     *
     * @template TRecord
     * @param list<TRecord> $records 当页记录
     * @param int $total 总条数
     * @param int|null $page 当前页码
     * @param int|null $size 每页条数
     * @return self<TRecord> 分页数据壳
     */
    public static function make(array $records, int $total, ?int $page = null, ?int $size = null): self
    {
        return new self($records, $total, $page, $size);
    }

    /**
     * 取当前页码。
     *
     * @return int|null 当前页码
     */
    public function page(): ?int
    {
        return $this->page;
    }

    /**
     * 取当页记录。
     *
     * @return list<T> 当页记录
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * 取每页条数。
     *
     * @return int|null 每页条数
     */
    public function size(): ?int
    {
        return $this->size;
    }

    /**
     * 转为数组。
     *
     * @return array{total: int, records: list<T>, page: int|null, size: int|null} 字段固定的数组
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'records' => $this->records,
            'page' => $this->page,
            'size' => $this->size,
        ];
    }

    /**
     * 取总条数。
     *
     * @return int 总条数
     */
    public function total(): int
    {
        return $this->total;
    }
}
