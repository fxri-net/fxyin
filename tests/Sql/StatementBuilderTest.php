<?php

declare(strict_types=1);

namespace Fxri\Tests\Sql;

use Fxri\Sql\Dialect\MysqlDialect;
use Fxri\Sql\StatementBuilder;
use Fxri\Sql\WhereBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * 语句构建器测试：四种骨架语句与条件拼接。
 */
final class StatementBuilderTest extends TestCase
{
    /**
     * 默认查询全部字段。
     */
    public function testSelect(): void
    {
        $built = StatementBuilder::make()->select('user');

        self::assertSame('SELECT * FROM `user`', $built->sql());
        self::assertSame([], $built->bindings());
    }

    /**
     * 指定字段时逐个包装。
     */
    public function testSelectColumns(): void
    {
        $built = StatementBuilder::make()->select('user', ['id', 'name']);

        self::assertSame('SELECT `id`, `name` FROM `user`', $built->sql());
    }

    /**
     * 方言片段作为字段时保留其绑定，条件绑定排在其后。
     */
    public function testSelectWithExpressionAndWhere(): void
    {
        $where = WhereBuilder::make()->eq('status', 1);
        $built = StatementBuilder::make()->select(
            'user',
            ['id', MysqlDialect::make()->fromUnixtime('created_at')],
            $where,
        );

        self::assertSame(
            'SELECT `id`, FROM_UNIXTIME(`created_at`, ?) FROM `user` WHERE `status` = ?',
            $built->sql(),
        );
        self::assertSame(['%Y-%m-%d %H:%i:%s', 1], $built->bindings());
    }

    /**
     * 查询字段不能为空。
     */
    public function testSelectRejectsEmptyColumns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatementBuilder::make()->select('user', []);
    }

    /**
     * 插入语句按行键展开字段。
     */
    public function testInsert(): void
    {
        $built = StatementBuilder::make()->insert('user', ['name' => 'fx', 'age' => 18]);

        self::assertSame('INSERT INTO `user` (`name`, `age`) VALUES (?, ?)', $built->sql());
        self::assertSame(['fx', 18], $built->bindings());
    }

    /**
     * 插入行不能为空。
     */
    public function testInsertRejectsEmptyRow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatementBuilder::make()->insert('user', []);
    }

    /**
     * 更新语句拼接条件。
     */
    public function testUpdate(): void
    {
        $where = WhereBuilder::make()->eq('id', 7);
        $built = StatementBuilder::make()->update('user', ['name' => 'fx'], $where);

        self::assertSame('UPDATE `user` SET `name` = ? WHERE `id` = ?', $built->sql());
        self::assertSame(['fx', 7], $built->bindings());
    }

    /**
     * 无条件时不追加 WHERE。
     */
    public function testUpdateWithoutWhere(): void
    {
        $built = StatementBuilder::make()->update('user', ['name' => 'fx']);

        self::assertSame('UPDATE `user` SET `name` = ?', $built->sql());
    }

    /**
     * 更新字段不能为空。
     */
    public function testUpdateRejectsEmptyRow(): void
    {
        $this->expectException(InvalidArgumentException::class);

        StatementBuilder::make()->update('user', []);
    }

    /**
     * 删除语句拼接条件。
     */
    public function testDelete(): void
    {
        $where = WhereBuilder::make()->lt('id', 10);
        $built = StatementBuilder::make()->delete('user', $where);

        self::assertSame('DELETE FROM `user` WHERE `id` < ?', $built->sql());
        self::assertSame([10], $built->bindings());
    }

    /**
     * 无条件时不追加 WHERE。
     */
    public function testDeleteWithoutWhere(): void
    {
        self::assertSame('DELETE FROM `user`', StatementBuilder::make()->delete('user')->sql());
    }
}
