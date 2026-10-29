<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Page;
use PHPUnit\Framework\TestCase;

/**
 * 分页数据壳测试：四键结构与空值保留。
 */
final class PageTest extends TestCase
{
    /**
     * 完整分页信息按固定键序输出。
     */
    public function testToArray(): void
    {
        $page = Page::make([['id' => 1], ['id' => 2]], 12, 2, 10);

        self::assertSame(2, $page->page());
        self::assertSame([['id' => 1], ['id' => 2]], $page->records());
        self::assertSame(10, $page->size());
        self::assertSame(12, $page->total());
        self::assertSame(
            ['total' => 12, 'records' => [['id' => 1], ['id' => 2]], 'page' => 2, 'size' => 10],
            $page->toArray(),
        );
    }

    /**
     * 页码与条数缺省为 null，但键仍保留。
     */
    public function testNullable(): void
    {
        $page = Page::make([], 0);

        self::assertNull($page->page());
        self::assertNull($page->size());
        self::assertSame(['total' => 0, 'records' => [], 'page' => null, 'size' => null], $page->toArray());
    }
}
