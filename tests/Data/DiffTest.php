<?php

declare(strict_types=1);

namespace Fxri\Tests\Data;

use Fxri\Data\Diff;
use Fxri\Exception\SameDataException;
use Fxri\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * 数据差异测试：变化收集、严格比较与失败分支。
 */
final class DiffTest extends TestCase
{
    /**
     * 收集变化的键与新值。
     */
    public function testChanges(): void
    {
        $diff = Diff::make(['name' => 'fx', 'age' => 18], ['name' => 'fx', 'age' => 20]);

        self::assertSame(['age'], $diff->keys());
        self::assertSame(['age' => 18], $diff->changes());
    }

    /**
     * 值类型不同也算变化。
     */
    public function testStrictCompare(): void
    {
        self::assertSame(['age'], Diff::make(['age' => '18'], ['age' => 18])->keys());
    }

    /**
     * 整数与浮点严格比较，1 与 1.0 算变化。
     */
    public function testIntegerVsFloat(): void
    {
        self::assertSame(['age'], Diff::make(['age' => 1.0], ['age' => 1])->keys());
    }

    /**
     * 嵌套数组键序不同也算变化：=== 对数组键序敏感。
     */
    public function testNestedKeyOrder(): void
    {
        $diff = Diff::make(
            ['tags' => ['a' => 1, 'b' => 2]],
            ['tags' => ['b' => 2, 'a' => 1]],
        );

        self::assertSame(['tags'], $diff->keys());
    }

    /**
     * 嵌套数组键序与值都一致时没有变化。
     */
    public function testNestedSame(): void
    {
        $this->expectException(SameDataException::class);

        Diff::make(['tags' => ['a' => 1, 'b' => 2]], ['tags' => ['a' => 1, 'b' => 2]]);
    }

    /**
     * 旧数据多出的键算变化，但不出现在新值集合里。
     */
    public function testRemovedKey(): void
    {
        $diff = Diff::make(['name' => 'fx'], ['name' => 'fx', 'age' => 18]);

        self::assertSame(['age'], $diff->keys());
        self::assertSame([], $diff->changes());
    }

    /**
     * 数据相同抛异常。
     */
    public function testSameThrows(): void
    {
        $this->expectException(SameDataException::class);

        Diff::make(['name' => 'fx'], ['name' => 'fx']);
    }

    /**
     * 缺少新数据抛校验异常。
     */
    public function testMissingNew(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('缺少新数据');

        Diff::make(null, []);
    }

    /**
     * 缺少旧数据抛校验异常。
     */
    public function testMissingOld(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('缺少旧数据');

        Diff::make([], null);
    }

    /**
     * 非数组入参抛校验异常。
     */
    public function testInvalidFormat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('数据格式错误');

        Diff::make('name', []);
    }

    /**
     * 失败分支不产生任何输出。
     */
    public function testNoOutput(): void
    {
        ob_start();
        try {
            Diff::make(['name' => 'fx'], ['name' => 'fx']);
        } catch (SameDataException) {
            // 异常本身就是预期结果，这里只看输出缓冲
        } finally {
            $output = ob_get_clean();
        }

        self::assertSame('', $output);
    }
}
