# 参数

请求数组进入业务逻辑之前，先按声明定形：保留哪些键、缺失时填什么、取到之后是否转换，都由一条可复用的规则描述。

## 定位

`Fxri\Param` 处理「已经解析成数组的一层入参」，上游是路由层、控制器或命令入口拿到的 `$_GET`、`$_POST`、JSON body 解码结果，下游通常是业务逻辑或 `Fxri\Sql` 的条件拼装。它不负责读取超全局变量，也不执行校验规则，只做定形与取值。

这一层由三个协作单元组成：`Definition` 把「键默认值」与「定形规则」绑成一个可复用单元，`Shape` 用链式方法描述定形规则，`Pipeline` 把多个定义按顺序串起来。`Merger`、`Picker`、`Json`、`Url` 是独立的取值与编解码工具，既可在链路中使用，也可单独调用。

与相邻层的分工：`Fxri\Data` 面向存储侧的数据结构与字段表达式，本层面向请求侧入参；`Fxri\Http` 负责请求上下文与响应信封，本层只接收它整理好的数组。参数本身不做标量强转，`'2'` 仍然是字符串。

所有类都不依赖内核，直接 `use` 即可。构造函数私有，实例一律由静态工厂或链式方法产出。

## 最小示例

```php
<?php

declare(strict_types=1);

use Fxri\Param\Definition;
use Fxri\Param\Json;
use Fxri\Param\Merger;
use Fxri\Param\Picker;
use Fxri\Param\Pipeline;
use Fxri\Param\Shape;
use Fxri\Param\Url;

// 假设这是从路由层拿到的原始入参，trace 是前端多传的键
$input = [
    'page' => '2',
    'keyword' => '',
    'with' => 'id, name ,, avatar',
    'trace' => 'drop-me',
];

// 第一环：只保留声明的键，空串按缺失处理并填 null
$clean = Pipeline::make(
    Definition::make(
        ['page' => 1, 'keyword' => null, 'with' => 'id'],
        Shape::only()->nullWhenBlank(),
    ),
    // 第二环：只把 with 转成数组，其余键原样带过
    Definition::make(['with' => 'id'], Shape::merge()->csv()),
)->resolve($input);

var_export($clean);
// array (
//   'page' => '2',
//   'keyword' => NULL,
//   'with' => array ( 0 => 'id', 1 => 'name', 2 => 'avatar' ),
// )

// 配置叠加：同名标量覆盖，同名数组逐层递归
$config = Merger::merge(
    ['db' => ['host' => '127.0.0.1', 'port' => 3306]],
    ['db' => ['port' => 3307]],
);

echo Picker::pluck($config, 'db.host');          // 127.0.0.1
var_export(Picker::only($config['db'], ['host', 'user']));
// array ( 'host' => '127.0.0.1' )，不存在的 user 被跳过

// JSON 串转数组，非法输入直接得到 null，不抛异常
var_export(Json::toArray('{"status":1}'));        // array ( 'status' => 1 )
var_export(Json::toArray('{'));                   // NULL

echo Url::build('/user/list', ['page' => 2, 'kw' => '风音']);
// /user/list?page=2&kw=%E9%A3%8E%E9%9F%B3

echo Url::withQuery('/user/list?page=2&kw=old#top', ['kw' => 'new', 'page' => null]);
// /user/list?kw=new#top，值为 null 的参数被删除，锚点保留
```

## 参数与返回值

### Definition

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(array $defaults, ?Shape $shape = null)` | 静态工厂；`$defaults` 既是键清单也是默认值来源，`$shape` 缺省为 `Shape::merge()` | `self` |
| `resolve(array $input)` | 用绑定的形状对输入定形，等价于 `$shape->apply($input, $defaults)` | `array<array-key, mixed>` |

### Shape

静态工厂：

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `merge()` | 以输入为基底，未声明的键原样保留；这也是 `Definition::make()` 的缺省规则 | `self` |
| `only()` | 只保留 `$defaults` 声明的键，未声明的键丢弃 | `self` |

实例方法，每个都返回新实例：

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `apply(array $input, array $defaults)` | 按当前规则定形并返回结果 | `array<array-key, mixed>` |
| `csv()` | 取到值后按逗号分隔转数组，空段丢弃 | `self` |
| `json()` | 取到值后按 JSON 串转数组，无法转换得到空数组 | `self` |
| `defaultsFirst()` | 默认值非 null 时直接采用默认值，忽略输入 | `self` |
| `fillWhenAbsent()` | 仅在键不存在时填充 | `self` |
| `fillWhenBlank()` | 键不存在或值为空串时填充 | `self` |
| `nullWhenAbsent()` | 把填充方式改为 `null`，触发条件沿用当前值；单独使用时等价于「键不存在则填 `null`」 | `self` |
| `nullWhenBlank()` | 键不存在或值为空串时填 `null`，同时把触发条件设为 `Blank` | `self` |

`apply()` 的处理顺序是固定的：先按 `BaseMode` 决定结果集起点，再逐键判断是否采用默认值，最后对 `$defaults` 声明的全部键执行转换。「是否存在」用 `isset()` 判断，因此输入值显式为 `null` 时按缺失处理。

### Pipeline

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `make(Definition ...$definitions)` | 静态工厂，可变参数，按传入顺序排列 | `self` |
| `add(Definition $definition)` | 在末尾追加一个定义，返回新实例 | `self` |
| `resolve(array $input)` | 依次执行每个定义，前一环的结果作为后一环的输入 | `array<array-key, mixed>` |

### Merger

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `merge(array $base, array $overlay, int $depth = -1)` | 递归合并，覆盖层同名值优先 | `array<array-key, mixed>` |
| `mergeKeepBlank(array $base, array $overlay, int $depth = -1)` | 同上，但覆盖层的空值（`null` 与 `''`）不覆盖基底已有的键 | `array<array-key, mixed>` |

`$depth` 是剩余递归层数：`0` 表示不递归、同名数组整体覆盖；正数表示最多下钻的层数，每下钻一层减一，减到 `0` 后整体覆盖；负数表示不限制深度。两个方法都不修改入参。

### Picker

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `only(array $source, array $keys)` | 只保留指定键，键不存在则跳过，不补 null | `array<array-key, mixed>` |
| `pluck(array $source, string $path, mixed $default = null)` | 按点号路径取值，如 `user.name`；中途某层不是数组或键不存在时返回 `$default` | `mixed` |

### Json

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `decode(string $value)` | 解码为 PHP 值，失败抛 `InvalidArgumentException` | `mixed` |
| `encode(mixed $value, int $flags = JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES)` | 编码为 JSON 串，默认不转义中文与斜杠，失败抛 `InvalidArgumentException` | `string` |
| `toArray(mixed $value)` | 数组原样返回；字符串尝试解码，空串、非法 JSON 或解码结果非数组时返回 `null` | `array<array-key, mixed>\|null` |

### Url

| 方法 | 说明 | 返回 |
| --- | --- | --- |
| `build(string $path, array $query = [], ?string $fragment = null)` | 拼装路径、查询串与锚点；查询串用 RFC3986 编码，空数组不加 `?`，空锚点不加 `#` | `string` |
| `query(string $url)` | 解析地址中的查询串，没有查询串返回空数组 | `array<array-key, mixed>` |
| `withQuery(string $url, array $query)` | 在原查询串上合并新参数，值为 `null` 的参数被删除，锚点保留 | `string` |

### BaseMode

| 分支 | 含义 |
| --- | --- |
| `Merge` | 以输入为基底，未声明的键原样保留 |
| `Only` | 只保留声明的键，未声明的键丢弃 |

### TriggerMode

| 分支 | 含义 |
| --- | --- |
| `Absent` | 仅当键不存在时触发填充 |
| `Blank` | 键不存在或值为空串时触发填充 |

### FillMode

| 分支 | 含义 |
| --- | --- |
| `Value` | 命中触发条件时填入声明的默认值 |
| `Null` | 命中触发条件时填入 `null`，适合只声明键位置的场景 |

### TransformMode

| 分支 | 含义 |
| --- | --- |
| `Csv` | 逗号分隔串转数组，逐项 `trim` 后丢弃空段 |
| `Json` | JSON 串转数组，无法转换的值得到空数组 |

`TransformMode::apply(mixed $value): array` 执行具体转换，返回值恒为数组：数组原样返回，数字按逗号串处理，其余类型得到空数组。

## 注意事项

- 构造函数全部私有，只能通过 `make()`、`Shape::merge()`、`Shape::only()` 取得实例；`add()` 与所有链式方法都返回新实例，忘记接住返回值等于没改。
- 「键是否存在」用 `isset()` 判断，输入中的 `null` 会被当成缺失，`fillWhenAbsent()` 同样会触发。要区分「显式传 null」与「完全没传」，需要在进入本层之前记录键名。
- `fillWhenBlank()` 在键存在时只把空串 `''` 当空，`0`、`'0'`、`false`、`[]` 都不算空；这与 `Fxri\Support\Type::isBlank()` 的语义一致。
- `defaultsFirst()` 只对非 null 默认值生效；默认值为 `null` 的键仍按输入与触发条件处理。
- `csv()` 与 `json()` 共享同一个转换位，后设置的会覆盖前一个；且转换会作用于该定义声明的所有键，需要按键分别转换时请拆成多个 `Definition` 走 `Pipeline`。
- `Pipeline::resolve()` 的结果是链式的：前一环按 `only` 丢掉的键，后一环用 `merge` 也补不回来。
- 本层不做标量强转与业务校验，`'2'` 仍是字符串；需要整数、枚举或区间校验时自行转换，失败时抛 `Fxri\Exception\ValidationException`（继承 `InvalidArgumentException`，消息不得包含敏感入参）。
- `Merger` 的 `$depth = 0` 是整体覆盖而非递归合并；`mergeKeepBlank()` 只认 `null` 与 `''`，`0` 与 `false` 会照常覆盖基底。
- `Json::decode()` 与 `Json::encode()` 抛 `InvalidArgumentException`；`Json::toArray()` 不抛，非法输入返回 `null`，适合「可缺省的配置项」场景。
- `Url::query()` 底层使用 `parse_str()`，键名中的点号与空格会被规范化成下划线；`Url::withQuery()` 删除参数必须传 `null`，传 `''` 只会写入空值。
