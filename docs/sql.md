# SQL

把条件、语句与方言拆成可组合的片段对象，让 SQL 文本与绑定值始终成对出现，拼装过程不与数据库连接耦合。

## 定位

`Fxri\Sql` 是纯静态能力里最靠近存储的一层，但它本身不碰存储：只产出 `Sql` 对象（一段 SQL 文本加一串绑定值），不持有连接、不执行查询、不管理事务。执行交回调用方的 PDO、mysqli 或框架自带的查询器。

它替代的是「把条件塞进位置数组」的旧写法。字段、值、比较方式、逻辑关系原先靠数字下标区分，加一种比较就要改一处 `switch`；现在比较方式收敛为 `WhereOperator` 枚举，连接方式收敛为 `Boolean`，大小写策略收敛为 `Collation`，链式方法名即语义。

与相邻层的关系：

- `Fxri\Data\Field` 依赖 `DialectInterface` 生成字段表达式，产出的也是 `Sql` 对象，可直接放进 `StatementBuilder::select()` 的 `columns` 参数。
- 方言层只收敛各数据库写法不同的字段引用、占位符与内置函数，通用的条件拼装留在 `WhereBuilder`，不下沉到方言。
- 值一律进绑定数组，不参与字符串拼接；字段名与表名只能经方言白名单校验后再引用。

需要手工拼装 `SELECT`、`INSERT`、`UPDATE`、`DELETE`，又不想把值拼进字符串时用它。项目已有成熟 ORM 或查询构造器时，这一层可以跳过。

## 最小示例

```php
<?php

declare(strict_types=1);

use Fxri\Sql\Boolean;
use Fxri\Sql\Collation;
use Fxri\Sql\Expression;
use Fxri\Sql\Sql;
use Fxri\Sql\StatementBuilder;
use Fxri\Sql\WhereBuilder;

$where = WhereBuilder::make()
    ->eq('status', 'enabled')
    ->and()
    ->group(static function (WhereBuilder $inner): void {
        $inner->like('name', '%风音%')
            ->or()
            ->eq(Expression::make('LEFT(`code`, 2)'), 'FX', Collation::CaseSensitive);
    }, Boolean::Or)
    ->isNotNull('deleted_at');

$statement = StatementBuilder::make()->select(
    'user',
    ['id', 'name', Sql::make('COUNT(*) AS total')],
    $where,
);

// SELECT `id`, `name`, COUNT(*) AS total FROM `user` WHERE `status` = ? OR (`name` LIKE ? OR LEFT(`code`, 2) = BINARY ?) AND `deleted_at` IS NOT NULL
echo $statement->sql() . PHP_EOL;

// 绑定值顺序与占位符出现顺序一致
// array(0 => 'enabled', 1 => '%风音%', 2 => 'FX')
var_export($statement->bindings());

// 以上只生成片段，真正执行需要调用方自己做
// $pdo->prepare($statement->sql())->execute($statement->bindings());
```

## 参数与返回值

### Sql

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $sql, array $bindings = [])` | 创建片段；`$bindings` 经 `array_values()` 重新索引，关联键不保留 | `self` |
| `sql()` | 取 SQL 文本或片段 | `string` |
| `bindings()` | 取绑定值列表，顺序即占位符出现顺序 | `array` |
| `isEmpty()` | SQL 文本是否为空串 | `bool` |

### Expression

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(string $sql)` | 创建原生片段；入参 trim 后为空串时抛 `InvalidArgumentException` | `self` |
| `sql()` | 取原生片段 | `string` |

`Expression` 只表示「这段是可信 SQL」，不做任何转义，构造即视作调用方已自行负责内容安全。

### WhereBuilder

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(?DialectInterface $dialect = null)` | 创建构建器；方言为 `null` 时用 `MysqlDialect::make()` | `self` |
| `and()` | 标记下一个条件用 `AND` 连接，默认值 | `self` |
| `or()` | 标记下一个条件用 `OR` 连接 | `self` |
| `not()` | 标记下一个条件取反，渲染为 `NOT (...)` | `self` |
| `eq` / `ne` / `gt` / `gte` / `lt` / `lte` | 追加 `=`、`<>`、`>`、`>=`、`<`、`<=` 条件；`eq`、`ne` 接受 `Collation`，默认 `CaseInsensitive` | `self` |
| `like(string\|Expression $column, mixed $value, Collation $collation = Collation::CaseInsensitive)` | 追加模糊匹配条件，通配符需调用方写入 `$value` | `self` |
| `in(string\|Expression $column, array $value)` | 追加集合包含条件；空数组生成恒假条件 `0 = 1` | `self` |
| `between(string\|Expression $column, array $value)` | 追加区间条件；数组元素必须恰好两个，否则抛 `InvalidArgumentException` | `self` |
| `isNull(string\|Expression $column)` | 追加为空条件，不产生绑定值 | `self` |
| `isNotNull(string\|Expression $column)` | 追加不为空条件，不产生绑定值 | `self` |
| `compare(WhereOperator $operator, string\|Expression $column, mixed $value = null, Collation $collation = Collation::CaseInsensitive)` | 按枚举追加任意比较条件 | `self` |
| `group(Closure $callback, ?Boolean $boolean = null)` | 追加括号分组；`$boolean` 非空时先覆盖下一个条件的连接方式；分组内无条件时整体忽略；`nested()` 等价于不传 `$boolean` 的 `group()` | `self` |
| `build()` | 渲染全部条件 | `Sql` |

渲染规则：第一个条件不带连接符；每个条件推入后连接方式复位为 `AND`、取反标记复位为 `false`；`not()` 只作用于紧随其后的一个条件（分组也算一个条件）。

### StatementBuilder

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(?DialectInterface $dialect = null)` | 创建构建器；方言为 `null` 时用 `MysqlDialect::make()` | `self` |
| `select(string $table, array $columns = ['*'], ?WhereBuilder $where = null)` | 生成查询语句；`$columns` 元素为字段名或 `Sql`，空数组抛 `InvalidArgumentException` | `Sql` |
| `insert(string $table, array $row)` | 生成插入语句；`$row` 为空抛 `InvalidArgumentException`，键为字段名、值为绑定值 | `Sql` |
| `update(string $table, array $row, ?WhereBuilder $where = null)` | 生成更新语句；`$row` 为空抛 `InvalidArgumentException` | `Sql` |
| `delete(string $table, ?WhereBuilder $where = null)` | 生成删除语句 | `Sql` |

绑定值顺序：`select` 先列字段片段携带的绑定，再接条件绑定；`update` 先 `SET` 的绑定，再接条件绑定；`delete` 只有条件绑定；`insert` 即 `$row` 的取值顺序。`$where` 为 `null` 或渲染结果为空片段时，语句不带 `WHERE`。

### WhereOperator

| 分支 | 含义 |
| --- | --- |
| `Eq` | 等于，渲染 `=` |
| `Ne` | 不等于，渲染 `<>` |
| `Gt` | 大于，渲染 `>` |
| `Gte` | 大于等于，渲染 `>=` |
| `Lt` | 小于，渲染 `<` |
| `Lte` | 小于等于，渲染 `<=` |
| `Like` | 模糊匹配，渲染 `LIKE` |
| `In` | 集合包含，渲染 `IN (?, ?, ...)` |
| `NotNull` | 不为空，渲染 `IS NOT NULL` |
| `Null` | 为空，渲染 `IS NULL` |
| `Between` | 区间，渲染 `BETWEEN ? AND ?` |

### Boolean

| 分支 | 含义 |
| --- | --- |
| `And` | 字符串值 `'AND'`，全部条件都要满足 |
| `Or` | 字符串值 `'OR'`，满足任一条件即可 |

### Collation

| 分支 | 含义 |
| --- | --- |
| `CaseInsensitive` | 默认；按字段自身的排序规则比较，占位符不加前缀 |
| `CaseSensitive` | 仅对 `eq`、`ne`、`like` 生效，占位符前加 `BINARY ` 走二进制比较 |

### DialectInterface 与 MysqlDialect

`DialectInterface` 与 `MysqlDialect` 位于 `Fxri\Sql\Dialect` 命名空间，`WhereBuilder::make()` 与 `StatementBuilder::make()` 的 `$dialect` 参数都接受该接口的实现。`MysqlDialect` 是随包的唯一实现，右列即接口方法在 MySQL 下的写法：

| 方法 | MySQL 实现 |
| --- | --- |
| `make()` | 创建实例，构造器为私有 |
| `field(string $name)` | 按 `.` 拆成至多两段，逐段校验并加反引号；`*` 原样输出，超过两段或含非法字符抛 `InvalidArgumentException` |
| `findInSet(string\|Expression $column, mixed $value)` | `FIND_IN_SET(?, col)`，值进绑定 |
| `fromUnixtime(string\|Expression $column, string $format = '%Y-%m-%d %H:%i:%s')` | `FROM_UNIXTIME(col, ?)`，格式串进绑定 |
| `ifnull(string\|Expression $column, mixed $default)` | `IFNULL(col, ?)`；`$default` 为 `null` 时输出 `IFNULL(col, NULL)` 且不产生绑定 |
| `jsonContains(string\|Expression $column, mixed $value)` | 先 `json_encode()` 再生成 `JSON_CONTAINS(col, ?)`；编码失败抛 `InvalidArgumentException` |
| `placeholder()` | 固定返回 `?` |

字段名白名单为 `[A-Za-z_][A-Za-z0-9_]*`，因此 `MysqlDialect::make()->field('user.id')` 得到 `` `user`.`id` ``，`field('user.*')` 得到 `` `user`.* ``，而 `field('user name')` 直接抛异常。

## 注意事项

- ⚠️ `Sql` 与 `Expression` 的构造器都是私有的，只能经 `make()` 创建；`Expression::make()` 会拒绝纯空白输入。
- ⚠️ `isEmpty()` 只看 SQL 文本是否为空串，不看绑定值；`WhereBuilder::make()->build()` 是空片段，`StatementBuilder` 遇到它会连 `WHERE` 一起省掉。
- ⚠️ `update()` / `delete()` 的 `$where` 传 `null` 会生成不带 `WHERE` 的全表语句，生产代码应显式传入条件。
- ⚠️ `select()` 的 `columns` 只接受 `string` 与 `Sql`。函数表达式必须包成 `Sql::make('COUNT(*) AS total')`，直接传 `Expression` 会被当作字段名送进白名单校验而抛异常。
- ⚠️ `Collation::CaseSensitive` 只改变 `eq`、`ne`、`like` 的占位符前缀，数值比较加 `BINARY` 会失去索引，不要用在 `gt`、`lte` 一类条件上。
- ⚠️ `in()` 收到空数组时输出 `0 = 1`，不会输出 `IN ()`，也不产生绑定值；经 `compare(WhereOperator::In, ...)` 传入非数组同样走这条恒假分支。
- ⚠️ `between()` 要求数组恰好两个元素且键被忽略（内部 `array_values()`），长度不符抛 `InvalidArgumentException`。
- ⚠️ `and()`、`or()`、`not()` 是「作用于下一个条件」的标记，条件推入后立即复位；连续写两次 `or()` 只有最后一次有效。
- ⚠️ 第一个条件前的连接符在渲染时被丢弃，`WhereBuilder::make()->or()->eq('a', 1)` 不会以 `OR` 开头。
- ⚠️ 空分组既不输出括号也不改变连接状态，避免后续条件被悬空的连接符带偏。
- ⚠️ `Expression` 不做转义，把外部输入拼进 `Expression::make()` 等同于直接拼接 SQL。
- 占位符固定为 `?`，只适用于 PDO 风格的位置绑定；命名占位符与 PostgreSQL 的 `$1` 不在当前方言覆盖范围内。
