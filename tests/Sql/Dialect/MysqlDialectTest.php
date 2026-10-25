<?php

declare(strict_types=1);

namespace Fxri\Tests\Sql\Dialect;

use Fxri\Sql\Dialect\MysqlDialect;
use Fxri\Sql\Expression;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * MySQL 方言测试：字段白名单与函数片段。
 */
final class MysqlDialectTest extends TestCase
{
    /**
     * 合法字段名按段包装。
     *
     * @param string $name 字段名
     * @param string $expected 期望结果
     */
    #[DataProvider('fieldProvider')]
    public function testField(string $name, string $expected): void
    {
        self::assertSame($expected, MysqlDialect::make()->field($name));
    }

    /**
     * 合法字段名对照表。
     *
     * @return array<string, array{string, string}> 用例集合
     */
    public static function fieldProvider(): array
    {
        return [
            '单段字段' => ['name', '`name`'],
            '带表名' => ['user.name', '`user`.`name`'],
            '全部字段' => ['*', '*'],
            '表名加全部字段' => ['user.*', '`user`.*'],
        ];
    }

    /**
     * 非法字段名一律拒绝。
     *
     * @param string $name 字段名
     */
    #[DataProvider('invalidFieldProvider')]
    public function testFieldRejectsInvalid(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        MysqlDialect::make()->field($name);
    }

    /**
     * 非法字段名对照表。
     *
     * @return array<string, array{string}> 用例集合
     */
    public static function invalidFieldProvider(): array
    {
        return [
            '空名' => [''],
            '反引号注入' => ['name` = 1 -- '],
            '含空格' => ['user name'],
            '三段' => ['db.user.name'],
            '数字开头' => ['1name'],
        ];
    }

    /**
     * 各函数输出固定片段并绑定参数。
     */
    public function testFunctions(): void
    {
        $dialect = MysqlDialect::make();

        $ifnull = $dialect->ifnull('name', '');
        $ifnullNull = $dialect->ifnull('name', null);
        $findInSet = $dialect->findInSet('tags', 'php');
        $fromUnixtime = $dialect->fromUnixtime('created_at');
        $jsonContains = $dialect->jsonContains('meta', ['a' => 1]);

        self::assertSame('IFNULL(`name`, ?)', $ifnull->sql());
        self::assertSame([''], $ifnull->bindings());
        self::assertSame('IFNULL(`name`, NULL)', $ifnullNull->sql());
        self::assertSame([], $ifnullNull->bindings());
        self::assertSame('FIND_IN_SET(?, `tags`)', $findInSet->sql());
        self::assertSame(['php'], $findInSet->bindings());
        self::assertSame('FROM_UNIXTIME(`created_at`, ?)', $fromUnixtime->sql());
        self::assertSame(['%Y-%m-%d %H:%i:%s'], $fromUnixtime->bindings());
        self::assertSame('JSON_CONTAINS(`meta`, ?)', $jsonContains->sql());
        self::assertSame(['{"a":1}'], $jsonContains->bindings());
    }

    /**
     * 原生表达式不做字段包装。
     */
    public function testExpressionColumn(): void
    {
        $built = MysqlDialect::make()->ifnull(Expression::make('COUNT(*)'), 0);

        self::assertSame('IFNULL(COUNT(*), ?)', $built->sql());
        self::assertSame([0], $built->bindings());
    }

    /**
     * 无法编码的值直接拒绝。
     */
    public function testJsonContainsRejectsUnencodable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MysqlDialect::make()->jsonContains('meta', INF);
    }

    /**
     * MySQL 使用问号占位符。
     */
    public function testPlaceholder(): void
    {
        self::assertSame('?', MysqlDialect::make()->placeholder());
    }
}
