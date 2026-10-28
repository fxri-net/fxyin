---
title: 2.0 API 对照
---

# 风音框架 2.0 API 对照

2.0 是一次不保留 API 兼容层的大版本改版。旧的门面层、全局函数、点分模式号与数字码配置全部移除，改由具名类与具名枚举承担。本文是 API 对照附录，逐项列出旧写法与新写法的对应关系。

> 升级步骤、接入形态与扩展目录迁移见[1.x 升级 2.0 指南](./upgrade-2.0)；本页只回答「旧写法对应什么新写法」。

## 一、改动概览

| 维度 | 1.x | 2.0 |
| --- | --- | --- |
| 安装方式 | 解压框架包到 `extend/fxri/base` | `composer require fxri/fxyin` |
| 加载方式 | 自带 `autoload.php` + `Loader` 注册 | Composer PSR-4，`Fxri\` → `src/` |
| 入口 | `\fxyin\App::init()` 隐式初始化 | `KernelBuilder::make()->build()` 显式装配 |
| 调用方式 | `\fxapp\Base::config()` 等静态门面 | `Kernel` 实例 + `Fxri\*` 纯静态工具类 |
| 模式选择 | 点分数字串（`1.1.2`）与整型 `type` 参数 | 具名枚举 + 链式方法 |
| 响应结构 | 配置驱动的 6 元位置数组 | `Envelope` 固定四字段 |
| 错误处理 | `fxy_echo` 返回数组、`throwable()` 直接 `exit` | 抛异常，由接入层统一映射为信封 |
| PHP 版本 | 7.1 ~ 8.4 | 8.2 起 |

## 二、安装与装配

~~~bash
composer require fxri/fxyin
~~~

最小装配示例：

~~~php
<?php

declare(strict_types=1);

use Fxri\KernelBuilder;
use Fxri\Translation\ArrayTranslator;

$kernel = KernelBuilder::make()
    ->withConfigDir(__DIR__ . '/config')
    ->withTranslator(ArrayTranslator::fromDirectory(__DIR__ . '/language/zh-cn'))
    ->build();

// 取配置
$name = $kernel->config()->get('app.name', '未命名');

// 取翻译
echo $kernel->trans('欢迎');
~~~

装配项说明：

| 方法 | 作用 |
| --- | --- |
| `withConfigDir(string $directory)` | 追加配置目录，后追加者覆盖同名文件 |
| `withLogger(LoggerInterface $logger)` | 注入 PSR-3 日志 |
| `withTranslator(TranslatorInterface $translator)` | 注入翻译器 |
| `withHttpClient(object $client)` | 注入 PSR-18 客户端 |
| `withAdapter(object $adapter)` | 注入接入方适配器（如 ThinkPHP 容器适配） |

配置文件按「文件名即分组名」加载，`config/app.php` 返回的数组整体位于 `app` 键下，用 `$kernel->config()->get('app.name')` 取值。

## 三、命名空间与类映射

| 旧 | 新 |
| --- | --- |
| `\fxyin\App` | `Fxri\KernelBuilder`、`Fxri\Kernel` |
| `\fxyin\Config` | `Fxri\Config\Config`、`Fxri\Config\ConfigLoader` |
| `\fxyin\Env` | `Fxri\Config\Environment`、`Fxri\Http\ServerContext` |
| `\fxyin\Lang` | `Fxri\Translation\ArrayTranslator` |
| `\fxyin\Cookie` | `Fxri\Http\Cookie\CookieJar` |
| `\fxyin\Dir` | `Fxri\File\Filesystem`、`Fxri\File\Path` |
| `\fxyin\Loader` | Composer PSR-4（不再需要） |
| `\fxyin\Facade` | 移除，改用 `Kernel` 与纯静态类 |
| `\fxapp\Base` | `Fxri\Kernel`、`Fxri\Http\*`、`Fxri\Translation\*` |
| `\fxapp\Client` | `Fxri\Http\ClientInfo` |
| `\fxapp\Data` | `Fxri\Data\*`、`Fxri\Sql\*` |
| `\fxapp\File` | `Fxri\File\*`、`Fxri\Support\Formatter` |
| `\fxapp\Math` | `Fxri\Support\Random` |
| `\fxapp\Param` | `Fxri\Param\*` |
| `\fxapp\Safe` | `Fxri\Security\*` |
| `\fxapp\Server` | `Fxri\Http\Envelope`、`Responder`、`ServerContext`、`RequestContext` |
| `\fxapp\Service` | `Fxri\Http\HttpClient\Client`、`ClientFactory` |
| `\fxapp\Text` | `Fxri\Text\*`、`Fxri\Support\Type` |
| `\fxapp\View` | `Fxri\Http\View`、`Fxri\Http\Renderer` |

旧门面 `\fxapp\Base` ~ `\fxapp\View` 与 `app/controller/facade/` 一并移除，不再提供任何转发入口。

## 四、数字码全部改为具名枚举

### 4.1 数字码总览

| 旧位置 | 旧数字码 | 新写法 |
| --- | --- | --- |
| `Param::define()` | `1.1.1`、`1.1.2` … | `Shape` 链式组合，见 4.2 |
| `Param::url()` | `1.1.1`、`1.1.3`、`1.1`、`1.2`、`2.1` | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| `facade.data.where` | `-1` ~ `6` | `WhereBuilder`，见 4.4 |
| `facade.server.echo.template` | `0` ~ `5` | `Envelope` 固定四字段，见 4.5 |
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
| `Server::format()` | `1`、`2` | `Envelope::toArray()` 或项目侧序列化，见 4.5 |

### 4.2 `Param::define()` 模式号 → `Shape`

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

### 4.3 `Param::url()` 模式号 → `Url`

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

### 4.4 `facade.data.where` 8 个数字键 → `WhereBuilder`

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

### 4.5 `server.echo` 两段配置 → `Envelope`

旧响应由 `facade.server.echo.template`（6 个位置）与 `facade.server.echo.format`（5 个位置到键名的映射）两段配置驱动：

| 旧位置 | 旧含义 | 新字段 |
| --- | --- | --- |
| `0` | 逻辑状态 | `Envelope::$ok`（`ok`） |
| `1` | 状态代码 | `Envelope::$code`（`code`） |
| `2` | 提示信息 | `Envelope::$message`（`message`） |
| `3` | 响应数据 | `Envelope::$data`（`data`） |
| `4` | 扩展数据 | 并入 `message` 或 `data`，不再单独留位 |
| `5` | 请求时间 | 由接入层自行补充，框架不再内置 |

~~~php
// 1.x
return \fxapp\Base::echo('请求成功', 0, $data);

// 2.0
use Fxri\Http\Envelope;

return Envelope::success($data, '请求成功');
~~~

注意两点行为变化：

- `code` 由整型改为字符串，成功固定为 `Envelope::OK`（`'ok'`），构造期不接受空串。
- 旧 `extend` 与 `time` 两个位置不再存在，需要保留时由接入层在 `Envelope::toArray()` 之外自行追加。

## 五、全局函数迁移

### 5.1 11 个 `is_*` 校验函数

| 旧函数 | 新写法 |
| --- | --- |
| `is_json($data)` | `Fxri\Support\Type::isJson()` |
| `is_blank($data)` | `Fxri\Support\Type::isBlank()` |
| `is_time($time, $format)` | `Fxri\Support\Type::isTime()` |
| `is_function($data)` | `Fxri\Support\Type::isCallable()` |
| `is_mobile($data)` | `Fxri\Support\Type::isMobile()` |
| `is_email($data)` | `Fxri\Support\Type::isEmail()` |
| `is_zipcode($data)` | `Fxri\Support\Type::isZipcode()` |
| `is_ipv4($data)` | `Fxri\Support\Type::isIpv4()` |
| `is_ipv6($data)` | `Fxri\Support\Type::isIpv6()` |
| `is_url($data)` | `Fxri\Support\Type::isUrl()` |
| `is_ip($data)` | `Fxri\Support\Type::isIp()` |

### 5.2 7 个 `fxy_*` 函数

| 旧函数 | 新写法 |
| --- | --- |
| `fxy_lang($name, $vars, $lang)` | `$kernel->trans($id, $parameters)` |
| `fxy_config(...$vars)` | `$kernel->config()->get($name, $default)` |
| `fxy_cookie(...$vars)` | `Fxri\Http\Cookie\CookieJar` |
| `fxy_env(...$vars)` | `Fxri\Http\ServerContext`、`Fxri\Config\Environment` |
| `fxy_dump(...$vars)` | `Fxri\Support\Dump::write(...)` |
| `fxy_json($var, $type, $param)` | `Fxri\Param\Json::encode()`、`Json::decode()` |
| `fxy_echo($message, $code, $data, $extend)` | `Fxri\Http\Envelope` + `Fxri\Http\Responder` |

## 六、响应与异常

旧 `Server::format()` 按 `type` 决定输出结构，`Base::throwable()` 在非调试环境下直接 `exit` 输出。2.0 改为「业务抛异常、接入层统一映射」：

| 场景 | 旧写法 | 新写法 |
| --- | --- | --- |
| 校验失败 | `return \fxapp\Base::echo('参数不合法', 0)` | `throw new Fxri\Exception\ValidationException('参数不合法')` |
| 数据未变化 | `return \fxapp\Data::updateContrast(...)` | `throw new Fxri\Exception\SameDataException('数据相同')` |
| 未知异常 | `\fxapp\Base::throwable('系统异常')` | 交接入层捕获并映射，如 `Fxri\Integration\ThinkPHP\ExceptionHandler` |
| 输出响应 | `\fxapp\Server::format($echo, 2)` | `Fxri\Http\Responder::format($envelope)` |

`Server::format($data, 2)` 的「按 `facade.server.echo.format` 映射键名」能力没有等价实现，键名已在 `Envelope::toArray()` 中固定，不再可配置。

## 七、门面方法逐项对照

### 7.1 `\fxapp\Base`

| 旧方法 | 新写法 |
| --- | --- |
| `load($file, $type)` | 无等价实现，项目侧 `require` / `require_once` |
| `lang($name, $vars, $lang)` | `$kernel->trans($name, $vars)` |
| `langList($name, $vars, $lang)` | 无等价实现，`ArrayTranslator::trans()` 不做语言相关拼接 |
| `langParse($name)` | 无等价实现，同上 |
| `config(...$vars)` | `$kernel->config()->get()` |
| `cookie(...$vars)` | `CookieJar::get()`、`CookieJar::set()` |
| `env(...$vars)` | `ServerContext::get()`、`Environment::get()` |
| `dump(...$vars)` | `Dump::write(...)` |
| `json($data, $type, $param)` | `Json::encode()`、`Json::decode()` |
| `ipv4($data, $type)` | `Ip::toLong()`、`Ip::fromLong()` |
| `crypt($data, $type, $param)` | `Cipher::encrypt()`、`Cipher::decrypt()`；旧数据用 `LegacyCipher::decrypt()` |
| `throwable($message)` | 抛异常，由接入层处理 |
| `echo($message, $code, $data, $extend)` | `Envelope::success()`、`Envelope::failure()` |

### 7.2 `\fxapp\Client`

| 旧方法 | 新写法 |
| --- | --- |
| `ip($type = 0)` | `ClientInfo::ip()`；需要整型时用 `Ip::toLong()` |
| `browser($type = -1)` | `ClientInfo::browser(CaseFormat::Original)` |
| `system($type = -1)` | `ClientInfo::system(CaseFormat::Original)` |
| `param($param, $method)` | 无等价实现，直接读 `RequestContext` 与 `ServerContext` |

### 7.3 `\fxapp\Data`

| 旧方法 | 新写法 |
| --- | --- |
| `where($data, $param)` | `WhereBuilder`，见 4.4 |
| `whereSafe($value, &$method, $case)` | 无等价实现，绑定参数后不再需要白名单 |
| `whereFilter($value, $method, $case)` | 无等价实现，同上 |
| `whereBuild($key, $value, $method)` | `WhereBuilder::eq()`、`in()`、`like()` 等 |
| `whereAssemble($data)` | `WhereBuilder::build()` |
| `whereMake($data, $type)` | `WhereBuilder::build()`；需要前缀时用 `Expression` 拼接 |
| `insert($table, $data)` | `StatementBuilder::insert()` |
| `update($table, $data, $param)` | `StatementBuilder::update()` |
| `updateContrast($data_new, $data_old)` | `Diff::make()`，未变化时抛 `SameDataException` |
| `storeUpperLevel(...)` | 无等价实现，项目侧递归查询 |
| `storeLowerLevel(...)` | 无等价实现，同上 |
| `fieldDistance(...)` | 无等价实现，用 `Sql\Expression` 书写 |
| `fieldInitial($field)` | 无等价实现，用 `Sql\Expression` 书写 |
| `fieldDivision(...)` | 无等价实现，用 `Sql\Expression` 书写 |
| `fieldJson($field, $replace, $param)` | `Field::json($column, $path, FieldJsonMode::Quoted)` |
| `fieldText($field, $replace, $param)` | `Field::text($column, $replace, FieldTextMode::Keep)` |
| `fieldDate($field, $replace, $param)` | `Field::date($column, DateGranularity::DateTime, $replace)` |
| `fieldAlias($data, $alias, $delimiter)` | 无等价实现，项目侧拼接 |
| `fieldEscape($data, $delimiter)` | 无等价实现，`Expression` 内自行反引号 |
| `htmlFilter($data, $flags)` | `Html::escape($data)` |
| `htmlRemove($data, $flags)` | `Html::strip($data, $allowed)` |
| `listToTree($list, ...)` | `Tree::fromList($list, $rootId, $idKey, $parentKey, $childKey)` |
| `paramRepair($param)` | 无等价实现，项目侧按需补键 |
| `paramFilter($param)` | `Data\Param::filter($param)` |
| `paramEmpty($param, $type)` | `Data\Param::missing($param, NullPolicy::Blank)` |
| `paramExist($param)` | `[] === Data\Param::missing($param, NullPolicy::StrictNull)` |

### 7.4 `\fxapp\File`

| 旧方法 | 新写法 |
| --- | --- |
| `formatSize($size)` | `Formatter::size($bytes)` |
| `getList($path, $ext, $limit)` | `Filesystem::findFiles($directory, $extensions, $maxDepth)` |
| `moveDirectory($oldpath, $newpath)` | `Filesystem::moveDirectory()`（整目录移动，不再扁平合并内容） |
| `deleteDirectory($path)` | `Filesystem::deleteDirectory()` |
| `getMediaInfo($var, $type)` | `VideoInfo::inspect()` 取视频元信息；类型互查用 `MediaInfo::mime()`、`MediaInfo::extension()` |

### 7.5 `\fxapp\Math`

| 旧方法 | 新写法 |
| --- | --- |
| `rand($length, $type)` | 类型 0：`Random::string($length, RandomCharset::Alnum, RandomCharset::Alpha)`；类型 1：`Random::string($length, RandomCharset::Numeric)` |

### 7.6 `\fxapp\Param`

| 旧方法 | 新写法 |
| --- | --- |
| `init()` | `Application\Cli::parse()` + `Bootstrap::withCliArguments()` |
| `define($param, $mode)` | `Shape` 链式组合，见 4.2 |
| `array($param, $limit, $filter)` | `Data\Cast::toArray($param, $limit)` |
| `object($param, $limit, $filter)` | `Data\Cast::emptyToObject($param, $limit)` |
| `pick($base, $data)` | `Picker::pick()` |
| `append(...$args)` | `Merger::append()` |
| `merge($limit, $cover, $type, ...$args)` | `Merger::merge()`、`Merger::mergeKeepBlank()` |
| `cover($args, $limit, $cover, $type)` | `Merger::merge()` |
| `url($data)` | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| `json($var, $type, $param)` | `Json::encode()`、`Json::decode()` |

### 7.7 `\fxapp\Safe`

| 旧方法 | 新写法 |
| --- | --- |
| `crypt($data, $type, $param)` | 新数据用 `Cipher::encrypt()` / `decrypt()`；旧数据用 `LegacyCipher::encrypt()` / `decrypt()` |
| `md5($data, $type)` | `Hash::md5($data, HashLength::Full32)` |
| `token($data, $type)` | 新令牌用 `Token::make()` / `verify()`；旧令牌用 `LegacyToken::encode()` / `decode()` |
| `rsapri($data, $type, $param)` | 私钥加密用 `Rsa::privateEncrypt()`；签名与解密用 `Rsa::sign()` / `decrypt()` |
| `rsapub($data, $type, $param)` | 公钥解密用 `Rsa::publicDecrypt()`；验签与加密用 `Rsa::verify()` / `encrypt()` |
| `rsapem($data)` | `Rsa::normalizePem()` |

### 7.8 `\fxapp\Server`

| 旧方法 | 新写法 |
| --- | --- |
| `echo()` | `Envelope::success()` 的模板等价物 |
| `ip()` | `ClientInfo::ip()` |
| `system($type)` | `ClientInfo::system(CaseFormat::Original)` |
| `format($data, $type)` | `Envelope::toArray()`、`JsonFormatter::format()` |
| `env()` | `ServerContext::fromGlobals()`、`Environment::fromFile()` |
| `branch()` | 无等价实现，项目侧读取自身配置 |

### 7.9 `\fxapp\Service`

| 旧方法 | 新写法 |
| --- | --- |
| `http($url, $data, $header, $method, $option)` | `HttpClient\Client::send()` |
| `httpDown($url, $file)` | 无等价实现，项目侧用 `HttpClient\Client` 自行落盘 |

### 7.10 `\fxapp\Text`

| 旧方法 | 新写法 |
| --- | --- |
| `uuid($mode, $param)` | `Uuid::make(UuidMode, $seed, UuidStyle)` |
| `implode($delimiter, $data, $explode, $unique)` | `Str::implode($glue, $values, $unique)` |
| `explode($delimiter, $data, $unique, $limit)` | `Str::explode($separator, $value, $unique)` |
| `splice($string, $value, $delimiter)` | `Str::splice($value, $append, $glue)` |
| `strlen($string, $start, $end)` | `Str::isLength($value, $start, $end)` |
| `letters($var, $in_charset, $out_charset)` | `Encode::letters($value, $inCharset, $outCharset)` |
| `letter($var, $in_charset, $out_charset)` | `Encode::letter($value, $inCharset, $outCharset)` |
| `timeRange($time, $start, $end)` | `Time::range($time, $start, $end)` |
| `timeChange($time, $type, $format)` | `Time::parse()`、`Time::format()` |
| `timeMilli($mtime)` | `Time::milli()` |
| `timeFormat($time, $param)` | `Time::relative($time, $param)` |
| `strEncode($data)` | `Encode::encodeAll($data)` |
| `strEncodeMerge($data)` | `Encode::encodeAll($data)`（递归辅助已合并） |
| `strDecode($data)` | `Encode::decodeAll($data)` |
| `strDecodeMerge($data)` | `Encode::decodeAll($data)`（递归辅助已合并） |
| `ipv4($data, $type)` | `Ip::toLong()`、`Ip::fromLong()` |
| `convert($data, $type)` | `Encode::convert($value, $type)` |
| `throwable($th, $type, $param)` | 无等价实现，接入层自行渲染 |

### 7.11 `\fxapp\View`

| 旧方法 | 新写法 |
| --- | --- |
| `echo($data, $type)` | `View::render()`、`Renderer::render()` |

## 八、接入方 `extend/fxri/data` 处置

旧分发方式下，接入方在本项目之外维护 `extend/fxri/data` 目录（框架包放在 `extend/fxri/base`）。该目录没有框架源码，全部是个性化数据与环境差异，按以下四类处置：

| 类别 | 典型内容 | 处置 |
| --- | --- | --- |
| 上收到框架 | 语言词条、请求环境（方案、主机、路径、方法、来源参数）、响应信封结构 | 框架已内置等价实现：语言词条用 `Translation\PhpFileLoader::loadDirectory()` 读入 `ArrayTranslator`；请求环境用 `ServerContext` / `RequestContext`；响应结构用 `Envelope` |
| 保留差异迁入项目配置 | 项目私有配置项、应用名称与目录、Cookie 与 Session 参数、媒体工具路径 | 迁入项目自己的配置目录，由 `KernelBuilder::withConfigDir()` 加载 |
| 项目侧改写机制 | 依赖 `facade.data.where` 数字位置拼条件的代码、继承旧门面的自定义类、依赖 `server.echo.format` 改键名的序列化 | 改用 `WhereBuilder`、`Envelope`，或改为组合调用 `Fxri\*`；不再支持继承门面 |
| 直接删除 | `extend/fxri/base`（框架本体）、旧引导文件 `autoload.php` / `config.php`、`app/store/common.php` 式启动脚本、三段数字键配置 | 删除，改由 Composer 安装与 `KernelBuilder` 装配 |

`app/language/{zh-cn,en-us}` 下的词条文件属于纯数据，可作为 `PhpFileLoader::loadDirectory()` 的输入直接复用。

## 九、迁移检查清单

- 全仓检索 `\fxapp\`、`\fxyin\`、`fxy_echo`、`fxy_config`、`fxy_lang`、`fxy_env`、`fxy_cookie`、`fxy_dump`、`fxy_json`，除本文档外应归零。
- 全仓检索点分模式号（形如 `'1.1.2'`）与 `facade.data.where`、`facade.server.echo`，应归零。
- 旧响应数组的下标访问（`$echo[0]` ~ `$echo[5]`）改为 `Envelope::isOk()`、`code()`、`message()`、`data()`。
- 旧 `\fxapp\Data::field*()` 生成的字符串 SQL 改为 `Data\Sql` 对象，绑定值随对象传递。
- 加密：旧 `Safe::crypt()` 产出的密文只能用 `Security\LegacyCipher::decrypt()` 读取，新数据统一用 `Cipher`。
- 依赖注入：把 `LoggerInterface`、`TranslatorInterface`、HTTP 客户端从门面静态调用改为 `KernelBuilder` 注入。
- 入口：删除 `\fxyin\App::init()` 与 `autoload.php` 引导，改为 Composer 自动加载 + `KernelBuilder`。

## 十、不再提供的能力

以下 1.x 入口不再存在；能力大多有替代，但调用方式必须改写：

- 门面静态转发（`Facade::__callStatic`）与 `\fxapp\*` 类名：改由 `Fxri\*` 与 `Application\Services`。
- 全局函数与 `app/function/` 目录约定：`Support\Type`、各服务，或 `Bootstrap::withFunctions()`。
- 配置驱动的六元响应结构（`server.echo.template` / `server.echo.format`）：改为 `Envelope` 固定四字段，`extend`、`time` 两个响应位移除。
- 点分模式号与整型 `type`：改为具名枚举与链式 `Shape`。
- `throwable()` 直接终止进程：改为抛异常，由接入层映射。
- 数据库连接与事务：fxyin 不接管，仍由项目数据库层负责。
- 递归上下级 SQL 与字段表达式：改为应用层 `Data\Hierarchy` 与 `Sql\Expression`，不再生成 MySQL 专有 SQL。

## 十一、高频调用与行为差异

### 11.1 高频调用对照

以下条目来自对多个 1.x 接入方的只读统计，按出现频次排序；项目自有方法（如 `oracle()`、`Elven::echo()`）需要项目侧保留实现。

| 旧写法 | 新写法 | 说明 |
| --- | --- | --- |
| `Base::lang($name, $vars)` | `$kernel->trans($id, $parameters)` | 旧的列表拼接与按语言切换连接符需项目侧保留 |
| `Base::oracle($name)` | 项目工厂或容器 | 项目自有定位器，fxyin 不提供 |
| `Param::define([$data, $keys], '1.2.3')` | `Shape` / `Pipeline` | 点分码逐一对照见第四节 |
| `View::echo($data, 'json')` | `Responder`、`JsonFormatter` | XML 与视图类型由项目渲染器承接 |
| `Server::format($echo, 2)` | `Envelope::toArray()` 或项目序列化 | 键名固定，不再由配置映射 |
| `Base::config($name)` | `$kernel->config()->get($name, $default)` | 配置不再支持运行期写入 |
| `Param::json($data, 'encode')` | `Param\Json::encode()`、`Json::decode()` | 模式改为方法 |
| `Param::merge($limit, ...$args)` | `Merger::merge()`、`Merger::mergeKeepBlank()` | 深度与空值语义显式化 |
| `Server::echo()` | `Envelope::make()`、`Envelope::success()` | 六元数组改为四字段信封 |
| `Text::throwable($e)` | 抛异常，由接入层映射 | 不再直接终止进程 |
| `Data::paramEmpty($data, $type)` | `Data\Param::missing($data, NullPolicy::Blank)` | 空值策略改为枚举 |
| `Data::fieldJson(...)` | `Field::json(...)` | 见 7.3 |
| `View::log($data, $type)` | PSR-3 `LoggerInterface` | 通过 `KernelBuilder::withLogger()` 注入 |
| `Service::http(...)` | `HttpClient\Curl::request()` / `send()` | 需要 PSR-7 与 PSR-18 实现时改用 `HttpClient\Client::send()` |
| `File::getList($path, $ext)` | `Filesystem::findFiles($directory, $extensions)` | 扩展名过滤与深度由参数表达 |
| `Data::whereMake($data, $type)` | `WhereBuilder::build()`、`Expression` | 条件与绑定值对象化 |
| `Data::fieldEscape(...)` | `Field::escape()` | 见 7.3 |
| `Service::httpDown(...)` | `HttpClient\Curl::download()` | 非 2xx 不写文件 |
| `Math::rand($length, $type)` | `Random::string($length, RandomCharset::Alnum, RandomCharset::Alpha)` | 字符集改为枚举，首字符集单独指定 |
| `Text::timeMilli($mtime)` | `Time::milli()` | 见 7.10 |
| `Text::explode()`、`Text::implode()` | `Text\Str::explode()`、`Str::implode()` | 去重与限长参数调整 |
| `Client::ip($type)` | `ClientInfo::ip()`、`Text\Ip::toLong()` | 整型地址由 `Ip` 转换 |
| `Db::startTrans()` 等 | 项目数据库层 | fxyin 不接管连接与事务 |

### 11.2 行为差异

| 维度 | 1.x | 2.0 |
| --- | --- | --- |
| 响应结构 | 配置驱动的六元位置数组 | `Envelope` 固定 `ok`、`code`、`message`、`data` |
| 业务码类型 | 整型（如 `200`） | 字符串（成功为 `ok`） |
| 扩展位与时间位 | `extend`、`time` 两个位置 | 移除，由接入方自行追加 |
| 错误处理 | 返回数组或直接 `exit` | 抛异常，由接入层映射 |
| 条件构造 | 字符串片段与白名单过滤 | 绑定参数与 `Sql` 对象 |
| 配置 | 允许加载期执行代码 | 只接受纯数组 |
| 门面 | `__callStatic` 按目录优先级查找 | 无门面层，改用显式类与内核 |
| 安装 | 解压到 `extend/fxri/base` | Composer 包 |
| PHP | 7.1–8.4 | 8.2 起 |

## 十二、相关文档

- [1.x 升级 2.0 指南](./upgrade-2.0)：升级路线、阶段与回滚。
- [接入形态手册](./upgrade-2.0-runtime)：ThinkPHP 6、ThinkPHP 5、非 ThinkPHP、无 Composer。
- [扩展目录迁移](./upgrade-2.0-extend)：`extend/fxri/data` 的逐项处理。
- [升级检查清单](./upgrade-2.0-checklist)：阶段清单与基线模板。
- [快速开始](./getting-started)：从零安装、装配与最小可运行示例。
- [更新日志](./changelog)：各版本完整变更条目，含 2.0 的分组说明。
