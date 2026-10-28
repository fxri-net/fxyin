# 数据

把存储侧的字段取值、空值判断、树形结构与新旧数据比较收敛成可直接调用的类，避免每个模型重复拼函数、重复写兜底分支。

## 定位

`Fxri\Data` 面向「写库之前或出库之后」的数据形态，与请求侧的 `Fxri\Param` 互补：Param 回答「缺失时填什么」，Data 回答「空串算不算空、字段怎么取、列表怎么组织」。

`Field` 是唯一依赖数据库方言的类。它把时间戳格式化、JSON 取值、文本兜底拼成 `Fxri\Sql\Sql` 片段，值一律走 `?` 占位符，方言只负责函数写法与字段引用。本层不连接数据库、不执行语句，调用方拿到 `sql()` 与 `bindings()` 后自行拼进 `SELECT` 或 `UPDATE`。默认方言是 `MysqlDialect`，其他数据库实现 `DialectInterface` 后传给 `Field::make()`。

`Tree`、`Diff`、`Html`、`NullPolicy` 不依赖数据库：前两者处理数组结构，`Html` 处理输出前的转义与去标签，`NullPolicy` 把「null 与空串是否都算空」交给调用方显式声明。

异常口径统一：格式不合法抛 `InvalidArgumentException` 或其子类 `Fxri\Exception\ValidationException`；差异比对未发现变化时抛 `Fxri\Exception\SameDataException`，调用方按异常分支处理，而不是检查返回值。

## 最小示例

```php
<?php

declare(strict_types=1);

use Fxri\Data\Diff;
use Fxri\Data\Field;
use Fxri\Data\FieldJsonMode;
use Fxri\Data\FieldTextMode;
use Fxri\Data\Tree;
use Fxri\Text\DateGranularity;

$field = Field::make();

// 时间戳按日期输出，值为 0 或 NULL 时落到兜底值
$created = $field->date('created_at', DateGranularity::Date, '未知');
echo $created->sql();
// IF(`created_at` = 0 OR `created_at` IS NULL, ?, FROM_UNIXTIME(`created_at`, ?))
var_export($created->bindings());
// array ( 0 => '未知', 1 => '%Y-%m-%d' )

// JSON 字段取 a.b，键名自动加双引号
$tag = $field->json('extra', 'a.b', FieldJsonMode::Quoted, '无');
echo $tag->sql();
// IF(JSON_VALID(`extra`), IFNULL(JSON_UNQUOTE(JSON_EXTRACT(`extra`, ?)), ?), ?)
var_export($tag->bindings());
// array ( 0 => '$."a"."b"', 1 => '无', 2 => '无' )

// 文本字段空或 NULL 时取兜底值，兜底值为整数时同步转 SIGNED
echo $field->text('title', 0, FieldTextMode::Keep)->sql();
// CONVERT(IF(LENGTH(`title`) = 0 OR `title` IS NULL, ?, `title`), SIGNED)

// 扁平列表转树：parent_id 等于根标识的行成为顶层
$list = [
    ['id' => 1, 'parent_id' => 0, 'name' => '根'],
    ['id' => 2, 'parent_id' => 1, 'name' => '子'],
];
$tree = Tree::fromList($list);
var_export($tree);
// array (
//   0 => array (
//     'id' => 1, 'parent_id' => 0, 'name' => '根',
//     '_child' => array (
//       0 => array ( 'id' => 2, 'parent_id' => 1, 'name' => '子' ),
//     ),
//   ),
// )

// 树回列表：前序展开，_child 键已被移除
var_export(Tree::toList($tree));
// array ( 0 => array ( 'id' => 1, ... ), 1 => array ( 'id' => 2, ... ) )

// 差异比对：逐键严格比较，被删除的键只出现在 keys()
$diff = Diff::make(
    ['name' => '新名', 'status' => 1],
    ['name' => '旧名', 'status' => 1, 'remark' => '旧备注'],
);
var_export($diff->changes());   // array ( 'name' => '新名' )
var_export($diff->keys());      // array ( 0 => 'name', 1 => 'remark' )
```

## 参数与返回值

### Field

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(?DialectInterface $dialect = null)` | 静态工厂，`$dialect` 缺省为 `MysqlDialect::make()` | `self` |
| `date(string\|Expression $column, DateGranularity $granularity = DateGranularity::DateTime, mixed $replace = null)` | 把 Unix 时间戳字段格式化为日期或时间；`$replace` 非 null 时用 `IF` 兜住 `0` 与 `NULL` | `Sql` |
| `json(string\|Expression $column, string $path, FieldJsonMode $mode = FieldJsonMode::Quoted, mixed $replace = '')` | 取 JSON 字段中的键值，空路径抛 `InvalidArgumentException` | `Sql` |
| `text(string\|Expression $column, mixed $replace = '', FieldTextMode $mode = FieldTextMode::Keep)` | 字段为空串或 `NULL` 时取兜底值，按模式决定是否转整数 | `Sql` |
| `alias(string $field, string $alias = '', string $delimiter = '.')` | 字段不含分隔符时补别名前缀，以分隔符开头时去掉前导 | `string` |
| `escape(string $field, string $delimiter = '.')` | 去掉既有反引号后按方言重新包装，重复调用不叠加 | `string` |
| `distance(string\|Expression $lngStart, string\|Expression $latStart, string\|Expression $lngEnd, string\|Expression $latEnd)` | 球面距离片段，单位公里 | `Sql` |
| `division(string\|Expression $dividend, string\|Expression $divisor)` | 除数为 0 时返回 0 的安全除法片段 | `Sql` |
| `initial(string\|Expression $column)` | 按 GBK 区间把中文首字映射为 A-Z 的片段 | `Sql` |

`$granularity` 决定 `FROM_UNIXTIME` 的格式串：

| 分支 | 格式串 |
| --- | --- |
| `DateGranularity::Date` | `%Y-%m-%d` |
| `DateGranularity::DateTime` | `%Y-%m-%d %H:%i:%s` |
| `DateGranularity::Time` | `%H:%i:%s` |

生成结果与绑定参数：

| 调用 | 生成的片段 | 绑定参数 |
| --- | --- | --- |
| `date('created_at')` | ``FROM_UNIXTIME(`created_at`, ?)`` | `['%Y-%m-%d %H:%i:%s']` |
| `date('created_at', DateGranularity::Date, '未知')` | ``IF(`created_at` = 0 OR `created_at` IS NULL, ?, FROM_UNIXTIME(`created_at`, ?))`` | `['未知', '%Y-%m-%d']` |
| `text('title')` | ``IF(LENGTH(`title`) = 0 OR `title` IS NULL, ?, `title`)`` | `['']` |
| `text('title', 0)` | ``CONVERT(IF(LENGTH(`title`) = 0 OR `title` IS NULL, ?, `title`), SIGNED)`` | `[0]` |
| `json('extra', 'a.b')` | ``IF(JSON_VALID(`extra`), IFNULL(JSON_UNQUOTE(JSON_EXTRACT(`extra`, ?)), ?), ?)`` | `['$."a"."b"', '', '']` |
| `json('extra', '$.a.b', FieldJsonMode::Raw)` | 同上 | `['$.a.b', '', '']` |

`$column` 传字符串时由方言校验并加引用符，传 `Fxri\Sql\Expression` 时按原生片段使用、不再转义。

### FieldJsonMode

| 分支 | 含义 |
| --- | --- |
| `Quoted` | 键名加双引号并补 `$` 前缀，如 `a.b` 变成 `$."a"."b"`；以 `[` 开头的段按原样保留 |
| `Raw` | 路径原样传入，适合已按 JSON 语法写好的完整路径 |

### FieldTextMode

| 分支 | 含义 |
| --- | --- |
| `Keep` | 按兜底值类型推断：兜底值是整数时同步转 `SIGNED` |
| `ReplaceEmpty` | 只做空值替换，无论兜底值类型都不转整数 |
| `CastInt` | 强制把整段结果转成 `SIGNED` |

### Tree

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `fromList(array $list, int\|string $rootId = 0, string $idKey = 'id', string $parentKey = 'parent_id', string $childKey = '_child')` | 扁平列表转树，父级等于 `$rootId` 的行作为顶层 | `array<int, array<array-key, mixed>>` |
| `toList(array $tree, string $childKey = '_child')` | 树按前序展开为扁平列表，输出不含 `$childKey` 字段 | `array<int, array<array-key, mixed>>` |

### Hierarchy

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `ancestors(array $list, int\|string $id, string $idKey = 'id', string $parentKey = 'parent_id')` | 由近及远返回祖先主键，父级缺失或成环时截断 | `array<int, int\|string>` |
| `descendants(array $list, int\|string $id, string $idKey = 'id', string $parentKey = 'parent_id')` | 按层级广度优先返回后代主键，重复与环状引用只记录一次 | `array<int, int\|string>` |

### Cast

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `toArray(mixed $value, int $limit = -1)` | 对象按属性展开为数组，递归处理；`$limit` 为 0 时不处理，负数不限制 | `mixed` |
| `emptyToObject(mixed $value, int $limit = -1)` | 只把空数组转为 `stdClass`，非空数组与列表保持数组，对应 1.x `Param::object()` | `mixed` |
| `toObject(mixed $value, int $limit = -1)` | 数组递归转为对象，空数组转为空对象以保证 JSON 输出 `{}` | `mixed` |

### Param

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `exist(array $data, NullPolicy $policy = NullPolicy::Blank)` | 至少存在一个非空值时返回 `true` | `bool` |
| `filter(array $data)` | 去掉 `null`，保留 `0`、空串与 `false` | `array<array-key, mixed>` |
| `missing(array $data, NullPolicy $policy = NullPolicy::Blank)` | 按空值策略列出缺失键 | `array<int, array-key>` |
| `repair(array $rows)` | 收集全部行的键并补空串，按主键名排序，保证每行结构一致 | `array<int, array<array-key, mixed>>` |

### Diff

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(mixed $new, mixed $old)` | 逐键严格比较新旧数据；任一为 `null` 或非数组抛 `ValidationException`，无变化抛 `SameDataException` | `self` |
| `changes()` | 变化键的新值，不含被删除的键 | `array<array-key, mixed>` |
| `keys()` | 变化的键名，含仅存在于旧数据、已被删除的键 | `array<int, array-key>` |

### Html

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `escape(mixed $value)` | 转义 `<`、`>`、`&`，不转义引号，不重复编码已有实体；数组按元素递归，非字符串原样返回 | `mixed` |
| `strip(mixed $value, ?string $allowed = null)` | 去除 HTML 标签，`$allowed` 为允许保留的标签串；数组按元素递归，非字符串原样返回 | `mixed` |

### NullPolicy

| 分支 | 含义 |
| --- | --- |
| `Blank` | `null` 与空串都算空 |
| `StrictNull` | 仅 `null` 算空 |

`isEmpty(mixed $value): bool` 按当前分支判断：`Blank` 委托 `Fxri\Support\Type::isBlank()`，`StrictNull` 等价于 `null === $value`。

## 注意事项

- `Field` 构造函数私有，用 `Field::make()` 取实例；默认方言为 MySQL，换库需要自行实现 `DialectInterface`，字段引用与函数写的差异都在方言里。
- `Field` 只产出片段，不补 `SELECT`、`AS` 或 `WHERE`；`?` 占位符按 `bindings()` 的顺序绑定，`date()` 的兜底值排在格式串之前。
- `date()` 只在 `$replace` 为 `null` 时省略 `IF`；传空串 `''` 仍会生成兜底分支，把空值替换成空串。
- `json()` 对 `Quoted` 模式会先去掉路径开头的 `$` 与 `.`，再逐段加引号；两种模式下路径为空白都会抛 `InvalidArgumentException`，`Quoted` 模式遇到连续点号（空段）同样抛。
- `json()` 的取值结果统一经过 `JSON_UNQUOTE`，数字与布尔也会以字符串形式返回，需要数值时在 PHP 侧或外层 SQL 再转。
- `text()` 的 `Keep` 与 `ReplaceEmpty` 只在兜底值为整数时不同：前者自动 `CONVERT(..., SIGNED)`，后者保持字符串。要稳定拿到字符串就传字符串兜底值。
- `FieldTextMode::CastInt` 会把整段结果强制转成有符号整数，非数字文本按 MySQL 规则转换，取回后不要当成原始文本使用。
- `Tree::fromList()` 主键重复时后出现的行覆盖先出现的行；列表项不是数组、缺少主键、父级不是整数或字符串都会抛 `InvalidArgumentException`。
- 父级不存在、父级等于自身、父级等于 `$rootId` 的行都保留为根节点；环状父子关系没有入口，会被兜底提到顶层，保证往返不丢数据。
- `Tree::toList()` 按前序展开，节点或子级不是数组时抛 `InvalidArgumentException`；`fromList()` 的结果转回列表后不再含 `_child` 键。
- `Diff::make()` 用 `===` 比较，`'1'` 与 `1`、`1` 与 `1.0` 都算变化；`changes()` 不含被删除的键，`keys()` 含，删除场景只看 `keys()` 会拿到旧键名。
- 无变化时 `Diff::make()` 直接抛 `SameDataException`（继承 `RuntimeException`），不要写成「返回空数组」的用法。
- `Html::escape()` 使用 `ENT_NOQUOTES`，单双引号保持原样；不重复编码已有实体，同一值多次转义不会叠加成 `&amp;amp;`。
- `Html::strip()` 的 `$allowed` 传 `null` 表示去掉全部标签，数组元素递归时沿用同一份白名单。
- `NullPolicy::Blank` 的「空」只有 `null` 与 `''`，`0`、`false`、`[]` 都不算空；需要把 `0` 当空时自行加判断。
