---
title: 数字码对照
---

# 数字码对照

1.x 用数字码在点分配置里选模式，2.0 改为具名枚举与链式组合。本页逐项列出旧数字码与新写法的对应关系；命名空间映射、全局函数与门面方法见[API 对照](./api)。

## 一、数字码总览

| 旧位置 | 旧数字码 | 新写法 |
| --- | --- | --- |
| `Param::define()` | `1.1.1`、`1.1.2` … | `Shape` 链式组合，见第二节 |
| `Param::url()` | `1.1.1`、`1.1.3`、`1.1`、`1.2`、`2.1` | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| `facade.data.where` | `-1` ~ `6` | `WhereBuilder`，见第四节 |
| `facade.server.echo.template` | `0` ~ `5` | `Envelope` 固定五字段，见第五节 |
| `facade.server.echo.format` | `1` ~ `5` | `Envelope::toArray()` |
| `Data::fieldJson()` | `1`、`2` | `FieldJsonMode::Quoted`、`FieldJsonMode::Raw` |
| `Data::fieldText()` | `1`、`2`、`3` | `FieldTextMode::Keep`、`ReplaceEmpty`、`CastInt` |
| `Data::fieldDate()` | `1`、`2`、`3` | `DateGranularity::DateTime`、`Date`、`Time` |
| `Data::whereMake()` | `1`、`2` | `WhereBuilder::build()`、`Sql\Expression` |
| `Data::paramEmpty()` | `1`、`2` | `NullPolicy::Blank`、`NullPolicy::StrictNull` |
| `Client::ip()` | `0`、`1` | `ClientInfo::ip()`、`Text\Ip::toLong()` |
| `Client::browser()`、`Client::system()` | `-1`、`1`、`2` | `CaseFormat::Original`、`Lower`、`Upper` |
| `Base::ipv4()`、`Text::ipv4()` | `encode`、`decode` | `Text\Ip::toLong()`、`Text\Ip::fromLong()` |
| `Math::rand()` | `0`、`1` | `RandomCharset::Alnum`、`RandomCharset::Numeric`；类型 0 的首字符用 `RandomCharset::Alpha` |
| `Text::uuid()` | mode `1`/`2`/`3`；style `1`~`4` | `UuidMode` + `UuidStyle` |
| `Safe::md5()` | `1`、`2` | `HashLength::Full32`、`HashLength::Short16` |
| `Base::load()` | `null`、`'data'` | `File\Loader::load()`、`File\Loader::read()` |
| `Server::format()` | `1`、`2` | `Envelope::toArray()` 或项目侧序列化，见第五节 |

## 二、`Param::define()` 模式号 → `Shape`

旧调用形如 `\fxapp\Param::define([$input, $defaults], '1.1.2')`，其中第一项是待定形数据、第二项是键与默认值。新写法把模式号拆成链式方法：

| 旧模式号 | 旧语义 | 新写法 |
| --- | --- | --- |
| `1.1.1` | 以输入为基底，键不存在时填默认值 | `Shape::merge()->fillWhenAbsent()` |
| `1.1.2` | 以输入为基底，键不存在或为空串时填默认值 | `Shape::merge()->fillWhenBlank()` |
| `1.1.3` | 同 `1.1.1`，且对声明的键做 JSON 解码 | `Shape::merge()->fillWhenAbsent()->json()` |
| `1.2.1` | 以输入为基底，键不存在时置 `null` | `Shape::merge()->nullWhenAbsent()` |
| `1.2.2` | 以输入为基底，键不存在或为空串时置 `null` | `Shape::merge()->nullWhenBlank()` |
| `1.2.3` | 同 `1.2.2`，且对声明的键做 JSON 解码 | `Shape::merge()->nullWhenBlank()->json()` |
| `1.2.4` | 以输入为基底，键值按逗号拆成数组 | `Shape::merge()->csv()` |
| `1.3.1` | 默认值非 `null` 时优先于输入 | `Shape::merge()->defaultsFirst()` |
| `1.3.2` | 同 `1.3.1`；默认值为 `null` 时按「不存在或空串」置 `null` | `Shape::merge()->defaultsFirst()->nullWhenBlank()` |
| `2.1.1` | 只保留声明的键，不存在时填默认值 | `Shape::only()->fillWhenAbsent()` |
| `2.1.2` | 只保留声明的键，不存在或为空串时填默认值 | `Shape::only()->fillWhenBlank()` |
| `2.2.1` | 只保留声明的键，不存在时置 `null` | `Shape::only()->nullWhenAbsent()` |
| `2.2.2` | 只保留声明的键，不存在或为空串时置 `null` | `Shape::only()->nullWhenBlank()` |

改造示例：

~~~php
// 1.x
$param = \fxapp\Param::define([$input, ['page' => 1, 'size' => 20]], '2.1.2');

// 2.0
use Fxri\Param\Shape;

$param = Shape::only()->fillWhenBlank()->apply($input, ['page' => 1, 'size' => 20]);
~~~

多个定形步骤串联时用 `Pipeline`：

~~~php
use Fxri\Param\Definition;
use Fxri\Param\Pipeline;
use Fxri\Param\Shape;

$param = Pipeline::make(
    Definition::make(['page' => 1], Shape::only()->fillWhenAbsent()),
    Definition::make(['filters' => []], Shape::merge()->fillWhenAbsent()->json()),
)->resolve($input);
~~~

## 三、`Param::url()` 模式号 → `Url`

旧 `Param::url($data)` 内部用 3 个点分模式号补默认值，再按 `$data['type']` 的 `1.1` / `1.2` / `2.1` 分发行为：

| 旧行为 | 新写法 |
| --- | --- |
| 补 `type` / `url` / `name` 默认值 | `Shape::merge()->fillWhenAbsent()` |
| 补 `param` 并 JSON 解码 | `Shape::merge()->fillWhenAbsent()->json()` |
| `type = 1.1` 由路径与参数拼装地址 | `Url::build($path, $query, $fragment)` |
| `type = 1.2` 解析当前地址并改写参数 | `Url::withQuery($url, $query)` |
| `type = 2.1` 从地址中取参数 | `Url::query($url)` |

~~~php
// 1.x
$url = \fxapp\Param::url(['type' => '1.1', 'url' => '/list', 'param' => ['page' => 2]]);

// 2.0
use Fxri\Param\Url;

$url = Url::build('/list', ['page' => 2]);
~~~

## 四、`facade.data.where` 8 个数字键 → `WhereBuilder`

旧配置以位置数字描述条件元组，改由具名方法与枚举承担：

| 旧键 | 旧含义 | 新写法 |
| --- | --- | --- |
| `-1` | 原始表达式 | `WhereBuilder::compare()` 或 `Sql\Expression::make()` |
| `0` | 键名 | 作为 `column` 参数直接传入 |
| `1` | 键值 | 作为 `value` 参数直接传入 |
| `2` | 方法（`=`、`in`、`like` 等） | `eq()`、`in()`、`like()` 等具名方法 |
| `3` | 逻辑（`and` / `or`） | `and()`、`or()` |
| `4` | 分组 | `group(Closure, ?Boolean)` |
| `5` | 取反 | `not()` |
| `6` | 大小写敏感 | `Collation::CaseSensitive` / `CaseInsensitive` |

~~~php
// 1.x
$where = \fxapp\Data::where([
    ['build', 'status', 'enabled', '=', 'and', '1', 0, 1],
    ['build', 'name', '风音', 'like', 'and', '1', 0, 1],
]);

// 2.0
use Fxri\Sql\Boolean;
use Fxri\Sql\Collation;
use Fxri\Sql\WhereBuilder;

$where = WhereBuilder::make()
    ->eq('status', 'enabled')
    ->and()
    ->like('name', '风音')
    ->build();
~~~

分组与取反：

~~~php
$where = WhereBuilder::make()
    ->eq('enabled', 1)
    ->and()
    ->group(static function (WhereBuilder $inner): void {
        $inner->eq('type', 'a')->or()->eq('type', 'b');
    }, Boolean::Or)
    ->not()
    ->build();
~~~

旧 `whereSafe()`、`whereFilter()`、`whereBuild()`、`whereAssemble()` 不再保留：`WhereBuilder` 全部使用绑定参数，字符串白名单过滤已无必要；`build()` 返回的 `Sql` 对象同时携带 SQL 片段与绑定值。

## 五、`server.echo` 两段配置 → `Envelope`

旧响应由 `facade.server.echo.template`（6 个位置）与 `facade.server.echo.format`（5 个位置到键名的映射）两段配置驱动，2.0 统一为固定五键外壳：

| 旧位置 | 旧含义 | 新字段 |
| --- | --- | --- |
| `0` | 逻辑状态 | 内部的 `success`，只做判断，不输出 |
| `1` | 状态代码 | `code`（整数业务码，成功缺省 200） |
| `2` | 提示信息 | `message` |
| `3` | 响应数据 | `data`；分页接口用 `Data\Page` 的四键壳 |
| `4` | 扩展数据 | `extend` |
| `5` | 请求时间 | `debug`（如 `debug.time`） |

~~~php
// 1.x
return \fxapp\Base::echo('请求成功', 0, $data);

// 2.0
use Fxri\Http\Envelope;

return Envelope::success($data, '请求成功');
~~~

注意两点行为变化：

- `code` 统一为整数，成功缺省为 `Envelope::SUCCESS`（`200`），失败用稳定的业务整数码。
- `extend` 与 `time` 不再是可配置位置：`extend` 是正式的扩展字段，调试信息（含耗时）放 `debug`；`debug` 恒常输出，空时为空对象。
