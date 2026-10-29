# 门面与分域映射

旧写法与新写法的逐项对照。完整对照表以仓库 `docs/migration-2.0.md` 为准，本页只保留高频项与判断原则。

## 一、命名空间与类

| 旧 | 新 |
| --- | --- |
| `\fxyin\App` | `Fxri\KernelBuilder`、`Fxri\Kernel` |
| `\fxyin\Config` | `Fxri\Config\Config`、`ConfigLoader` |
| `\fxyin\Env` | `Fxri\Config\Environment`、`Fxri\Http\ServerContext` |
| `\fxyin\Lang` | `Fxri\Translation\ArrayTranslator` |
| `\fxyin\Cookie` | `Fxri\Http\Cookie\CookieJar` |
| `\fxyin\Dir` | `Fxri\File\Filesystem`、`Fxri\File\Path` |
| `\fxyin\Loader` | Composer PSR-4，不再需要 |
| `\fxyin\Facade` | 移除，改用 `Kernel` 与纯静态类 |
| 基类门面 | `Fxri\Kernel`、`Fxri\Http\*`、`Fxri\Translation\*` |
| 客户端门面 | `Fxri\Http\ClientInfo`、`RequestInput` |
| 数据门面 | `Fxri\Data\*`、`Fxri\Sql\*` |
| 文件门面 | `Fxri\File\*`、`Fxri\Support\Formatter` |
| 随机门面 | `Fxri\Support\Random` |
| 参数门面 | `Fxri\Param\*` |
| 安全门面（摘要、加解密、令牌） | `Fxri\Security\*` |
| 服务端门面 | `Fxri\Http\Envelope`、`Responder`、`ServerContext`、`RequestContext` |
| 服务门面 | `Fxri\Http\HttpClient\Client`、`ClientFactory`、`Curl` |
| 文本门面 | `Fxri\Text\*`、`Fxri\Support\Type` |
| 视图门面 | `Fxri\Http\View`、`Renderer` |

旧门面与其控制器侧转发一并移除，不提供任何转发入口；过渡层只能建在接入项目内。

## 二、点分模式号 → 具名枚举

| 旧写法 | 新写法 |
| --- | --- |
| 参数定形的 `1.1.x` 模式号 | `Shape` 链式组合（`merge()` / `only()` + 填充与转换方法） |
| URL 处理的 `1.1.1`、`1.2`、`2.1` | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| 条件构造的数字键 | `WhereBuilder` 链式方法 |
| 响应模板 0–5、格式化 1–5 | `Envelope::toArray()` 固定五键 |
| 字段 JSON 的 `1`、`2` | `FieldJsonMode::Quoted`、`FieldJsonMode::Raw` |
| 字段文本的 `1`、`2`、`3` | `FieldTextMode::Keep`、`ReplaceEmpty`、`CastInt` |
| 字段日期的 `1`、`2`、`3` | `DateGranularity::DateTime`、`Date`、`Time` |
| 空值策略的 `1`、`2` | `NullPolicy::Blank`、`NullPolicy::StrictNull` |
| 大小写形态 `-1`、`1`、`2` | `CaseFormat::Original`、`Lower`、`Upper` |
| UUID 的 mode 与 style | `UuidMode` + `UuidStyle` |
| 摘要长度 `1`、`2` | `HashLength::Full32`、`HashLength::Short16` |
| 随机字符范围 `0`、`1` | `RandomCharset::Alnum`、`Numeric`（首字符用 `Alpha`） |

判断原则：看到数字参数先找同名枚举，不再保留整型分支。

## 三、Param

| 旧方法 | 新写法 |
| --- | --- |
| 参数初始化 | `Application\Cli::parse()` + `Bootstrap::withCliArguments()` |
| 参数定形 | `Definition` + `Shape`，多环用 `Pipeline` |
| 数组化 / 对象化 | `Data\Cast::toArray()`、`Data\Cast::emptyToObject()` |
| 取值挑选 | `Picker::pick()` / `Picker::pluck()` |
| 数组追加 | `Merger::append()` |
| 数组合并 | `Merger::merge()`、`Merger::mergeKeepBlank()` |
| URL 处理 | `Url::build()`、`Url::query()`、`Url::withQuery()` |
| JSON 编解码 | `Param\Json::encode()`、`decode()`、`toArray()` |

## 四、Data

| 旧方法 | 新写法 |
| --- | --- |
| 条件构造与拼接 | `Sql\WhereBuilder`，绑定值随对象传递 |
| 语句拼装 | `Sql\StatementBuilder::insert()` / `update()` |
| 新旧数据比对 | `Data\Diff::make()`，无变化抛 `SameDataException` |
| 层级查询 | `Data\Hierarchy::ancestors()` / `descendants()` |
| 字段表达式 | `Data\Field::date()` / `json()` / `text()` / `distance()` / `division()` / `initial()` / `alias()` / `escape()` |
| HTML 过滤 | `Data\Html::escape()`、`strip()` |
| 列表转树 | `Data\Tree::fromList()`、`toList()` |
| 行结构修复 | `Data\Param::repair()` / `filter()` / `missing()` / `exist()` |

旧的条件构造需要调用方自行维护字符串白名单，v2 由绑定参数覆盖转义，不再需要白名单。

## 五、Text

| 旧方法 | 新写法 |
| --- | --- |
| 字符串拆合 | `Text\Str::explode()` / `implode()` / `splice()` |
| 长度校验 | `Text\Str::isLength()`、`length()` |
| 编码转换与首字母 | `Text\Encode::letters()`、`letter()`、`convert()` |
| 查询串编解码 | `Text\Encode::encodeAll()`、`decodeAll()`、`queryEncode()`、`queryDecode()` |
| IP 换算 | `Text\Ip::toLong()`、`fromLong()`、`toBinary()`、`fromBinary()` |
| 时间区间 | `Text\Time::range()` + `TimeRangeOptions` |
| 时间转换 | `Text\Time::change()` + `TimeOutput` |
| 毫秒处理 | `Text\Time::milli()`、`milliFormat()`、`milliParse()` |
| 时长描述 | `Text\Time::duration()`、`decompose()` |
| UUID | `Text\Uuid::make(UuidMode, $seed, UuidStyle)`；格式化现有串用 `format()` |
| 异常输出 | 改为抛异常，由接入层映射为信封 |

## 六、Client

| 旧方法 | 新写法 |
| --- | --- |
| 客户端地址 | `ClientInfo::ip()`；要整型再走 `Ip::toLong()` |
| 浏览器与系统 | `ClientInfo::browser(CaseFormat::Original)`、`system()` |
| 入参读取 | `RequestInput::all()` / `query()` / `post()` / `body()` / `cli()` / `only()` |
| Cookie 读写 | `CookieJar::get()` / `set()` / `delete()` / `signed()` |

## 七、Server

| 旧方法 | 新写法 |
| --- | --- |
| 响应输出 | `Envelope::success()` / `failure()` + `Responder` |
| 响应序列化 | `Envelope::toArray()`、`JsonFormatter::format()` |
| 服务端变量 | `ServerContext::fromGlobals()`、`RequestContext::fromGlobals()` |
| 请求方法与地址 | `RequestContext::method()` / `url()` / `header()` |
| 分支解析 | `BranchContext::resolve()`，分支配置由调用方写入 `Config\Store` |
| 系统标识 | PHP 原生 `PHP_OS_FAMILY` |
| 公网地址 | `HttpClient\Curl` + `Json` 组合 |

## 八、全局函数

| 旧 | 新 |
| --- | --- |
| `is_blank`、`is_json`、`is_time`、`is_email`、`is_ip`、`is_mobile`、`is_url`、`is_zipcode` 等 | `Support\Type` 的同名 `is*` 方法 |
| `fxy_json` | `Param\Json` |
| `fxy_lang` | `Kernel::trans()` / `Translation\Language` |
| `fxy_config` | `$kernel->config()->get()` |
| `fxy_env` | `Config\Store` 的 `env` 命名空间 |
| `fxy_cookie` | `CookieJar` |
| `fxy_dump` | `Support\Dump::write()` |
| `fxy_echo` | `Envelope` |

## 九、行为差异与不再提供的能力

- 异常取代 `exit`：旧实现失败即终止并输出，新实现抛异常，由接入层统一映射。
- 响应键名固定：旧配置驱动的键名映射没有等价实现。
- 已编码串不再二次编码：JSON 处理前先用 `Type::isJson()` 判断。
- 协议变更不回滚：框架升级与对外响应协议分开排期。
- 过渡层只减不增：项目内适配层最终必须删除。

更多差异与完整表见仓库 `docs/migration-2.0.md`，对等矩阵见 `docs/parity-1.6.1.md`。
