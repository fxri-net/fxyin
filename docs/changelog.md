---
outline: [2, 2]
---

# 更新日志

> 完整变更历史以随包发布的 CHANGELOG.md 为准，本页由 `pnpm sync:changelog-doc` 从根 CHANGELOG.md 自动同步，请勿手改。

## 2.2.1

> 2026-10-31 发布

### ⚡ 优化改进

- npm 包名统一为 `@fxri/fxyin`，变更集链路可正常消费

### 📝 文档更新

- 文档站更新日志补齐 2.2.0 版本块

## 2.2.0

> 2026-10-30 发布

> 本次含破坏性变更：对外响应统一为五键结构，接入方需按迁移文档改写响应读取。

### 🚨 重大变更

- 对外响应统一为 `code`、`message`、`data`、`extend`、`debug` 五键，`code` 为整数、默认 200，`success` 只在内部保留
- `Envelope`、`Responder`、JSON/XML 格式化器与 ThinkPHP 异常处理同步新口径，旧六元数组与 `isOk()` 别名不再保留
- `Security\LegacyCipher`、`LegacyToken` 更名为 `DesCipher`、`DesToken`

### ✨ 新增功能

- 统一命令行入口 `fxyin`，仓库内用 `php fxyin <命令>`，接入项目经 Composer bin 用 `php vendor/bin/fxyin <命令>`
- 命令域 `skills`、`upgrade`、`scan` 随包提供，`skills` 为原生实现；`package`、`parity` 保留为本仓内部命令
- `skills/` 随包提供两个 AI 技能——日常接入 `fxri-fxyin` 与 1.x 升级 `fxri-fxyin-upgrade`，含 `references/` 目录
- `Fxri\Tooling\SkillInstaller` 在项目面目录生成技能入口薄壳，指向包内真源，不复制正文、不写全局目录
- `tools/install-skills.php`（`--target`、`--check`、`--root`）供本仓库开发期使用，脚本不随包分发
- `Fxri\Tooling\MetaGenerator` 渲染并校验 `.phpstorm.meta.php`，按字符串名称取对象的工厂方法可补全出具体类
- `ConfigLoader` 支持递归扫描子目录与点号文件名
- 分页壳 `Data\Page`（`total`、`records`、`page`、`size`），分页列表统一放在 `data.records`
- `Envelope`、`Page`、`Formatter`、`Responder` 补充 `@template` 泛型声明，编辑器可推导具体类型
- 文档站补充「IDE 元数据」与「AI 技能」两页

### ⚡ 优化改进

- `psr/log` 约束放宽为 `^1.1 || ^2.0 || ^3.0`，可与锁定 1.x 的宿主框架共存
- 发布归档排除本地安装产物、文档站工具链与协作元数据，分发包不再携带 `node_modules`、`vendor` 与协作规则文件
- 技能版本在 frontmatter 与正文首部双写，便于核对是否同步

### 🔧 功能调整

- 升级体检工具与对等矩阵快照随包分发，接入项目可直接运行 `vendor/bin/fxyin upgrade`
- `composer.json` 声明 `bin`，`scan`、`package`、`parity`、`upgrade:inspect` 脚本改走统一入口
- 两个 AI 技能的许可证改为 `Apache-2.0`，与本包一致
- `fxri-fxyin` 技能补充命令行用法（`references/cli.md`），版本升至 1.1.0
- `fxri-fxyin-upgrade` 技能命令改为 `vendor/bin/fxyin upgrade`，版本升至 1.0.2

## 2.1.2

> 2026-10-29 发布

> 本次发布包与 2.1.1 的差异只有版本号常量与本段更新日志，无任何行为变更；升级体检工具与 1.x 升级文档随仓库提供，不进分发包。

### 🐛 问题修复

- 已发布版本标签重新指回发布记录对应的提交，标签谱系与包仓库记录恢复一致，`git describe` 不再回落到 2.0.0

### ⚡ 优化改进

- 升级体检工具按项目配置解析应用目录、命名空间、扩展目录与全局函数，不再写死默认目录
- 升级体检工具识别框架副本内的本地新增方法，并区分手写路由入口与风音 1.x 原生入口
- 1.x 升级文档补充框架副本本地改动盘点、本地新增方法落点、ThinkPHP 8 接入与手写路由入口处理

## 2.1.1

> 2026-10-28 发布

### ⚡ 优化改进

- 内置资源与全库统一 LF 行尾，语言包字节校验不再因平台差异出现本地通过、CI 失败
- 门禁新增 PHP 8.5 废弃函数静态检查，本地即可拦截较旧 PHP 运行时看不见的废弃调用
- 编码检查覆盖 CRLF、BOM 与非 UTF-8，乱码资源无法进入分发包
- 发布包卫生检查校验行尾规则与开发配置导出排除，根因修复被误删时直接报错
- 门禁前置检查 OpenSSL 可用性，环境缺失时给出明确指引而非误导性用例失败

### 🐛 问题修复

- 移除 PHP 8.5 已废弃的 curl_close 调用，避免升级到 8.5 后出现废弃告警

## 2.1.0

> 2026-10-28 发布

### ✨ 新增功能

- 内置中英文语言包随包提供，`KernelBuilder` 默认装配并生成 `Translation\Language`，支持嵌套词条、`%name%`/`{:name}`/`sprintf` 占位符与请求语言检测
- 默认配置随包提供，新增 `Config\Store` 运行期覆盖与 `Kernel::configStore()`，只读 `config()` 保持不变
- 新增 `Translation\Catalog`、`Translator`、`LocaleResolver`、`Language`，项目语言目录可覆盖内置词条
- 新增 `Application\Bootstrap`、`Services`、`Module` 与可选 `Router`，承接目录扫描、启动钩子、服务注册与控制器解析
- 新增 `Application\Cli` 与 `Bootstrap::withCliArguments()`，解析 `@a.b=值` 命令行参数并兜底 `env.base.host` / `env.base.uri`
- 新增 `Http\RequestInput` 聚合查询串、表单、JSON 请求体与命令行入参，`Picker::pick()` 按结构提取并补空
- `File\Filesystem::findFiles()` 支持递归列文件、扩展名过滤与深度限制
- 新增 `Security\Rsa::privateEncrypt()` / `publicDecrypt()` 与 `RsaPadding`，支持私钥加密、公钥解密与无填充
- 新增 `HttpClient\Curl` / `CurlResponse` / `CurlOptions`，以 URL 串发起请求、读取响应头与下载
- 新增 `RandomCharset::Alpha`，`Random::string()` 可单独限定首字符集
- 新增 `File\VideoInfo` 解析视频时长/分辨率/编码，`Security\LegacyToken` 读写 1.x 旧令牌
- 新增 `Time::decompose/duration`、`TimeUnit`、`TimeOutput`、`TimeRangeOptions`、`Time::milliFormat/milliParse`、`Uuid::format`、`EncodeConvert`，时长、时间输出与编码转换全部改用枚举/值对象
- 新增 `Cast::emptyToObject()` 与 `Merger::append()`，并让 `Language::phrase()` 支持 `!@`/`!#` 标记与首字母大写
- 补齐 1.6.1 能力：`File\Loader`、`Data\Cast`/`Hierarchy`、字段距离/首字母/除法/别名/转义、`Rsa::normalizePem`、`Http\BranchContext`/`XmlFormatter`、`Client::download`、`Time::range`/`change`/`relative`、`Encode` 嵌套编码与首字母等

### 🔧 功能调整

- `KernelBuilder` 默认装配内置语言与默认配置，可用 `bare()`、`withoutBuiltinLanguage()`、`withoutDefaultConfig()` 关闭；`withConfigDir()` 的项目配置覆盖内置配置
- 内核构造首参接受 `Config|Store`，`config()` 始终返回只读基底
- 应用启动与路由均需显式调用，不接管请求生命周期
- `ServerContext::fromGlobals()` 与 `RequestContext::fromGlobals()` 支持缺失变量兜底，真实变量优先
- 1.x `Env` 运行期叠加由 `Config\Store` 的 `env` 命名空间承接，`Config\Environment` 只解析 `.env` 文本

### ⚡ 优化改进

- 逐方法复核 92 个门面、18 个全局函数与 27 个运行时方法，修正 `Text` 编解码/长度/时长/毫秒、`Math` 随机、`View` 序列化、`Env`/`fxy_env` 叠加、`Data` 层级、`Param` 对象/追加、`Uuid` 格式化、`File` 视频信息与旧令牌等矩阵目标
- 对等矩阵、golden 测试与文档站对等说明补齐，1.6.1 能力项全部有可落地的 v2 目标（等价或覆盖）
- 内置资源随包分发，发布包卫生检查覆盖 `resources`；`ext-curl` 列为建议依赖，未安装时 URL 级 HTTP 调用抛出明确异常

## 2.0.1

> 2026-10-28 发布

### 🐛 问题修复

- 发布包不再包含 `.tasks`、`.trae`、`.agents` 与 1.x 的 `app/language`；`KernelBuilder::withConfigDir()` 对不存在的目录抛 `InvalidArgumentException`，避免静默得到空配置。

## 2.0.0

> 2026-10-25 发布

### 🚨 重大变更

- 移除以 `\fxapp\*` 与 `\fxyin\*` 为入口的门面层与全局函数，改由不可变 `Kernel` 实例与 `Fxri\*` 纯静态类承担，不再提供任何转发入口
- 点分模式号（如 `1.1.2`）与整型 `type` 参数全部改为具名枚举与链式方法，旧调用方式不再可用
- 响应结构由配置驱动的 6 元位置数组改为 `Envelope` 固定四字段，`extend`、`time` 两个响应位一并移除
- 错误处理由返回数组、`throwable()` 直接终止进程改为抛异常，交由接入层统一映射为信封
- 分发方式由解压框架包到 `extend/fxri/base` 改为 Composer 包 `fxri/fxyin`，自带 `autoload.php`、`Loader` 与 `\fxyin\App::init()` 一并移除
- 最低 PHP 版本由 7.1 提升到 8.2，全仓使用 `declare(strict_types=1)`

### ✨ 新增功能

- `Kernel` 与 `KernelBuilder` 组合根，支持追加配置目录、注入 PSR-3 日志、注入翻译器、注入 PSR-18 客户端与接入方适配器
- `Param\Shape` 以链式方法表达填充策略，涵盖缺失填默认、空白填默认、缺失置空、空白置空与按需 JSON 解码
- `Sql\WhereBuilder`、`StatementBuilder`、`Expression` 与 `Dialect\MysqlDialect`，条件与语句构造改为对象化并携带绑定值
- `Http\Envelope`、`Responder`、`JsonFormatter`、`Renderer`、`View`，统一响应信封与输出渲染
- `Http\HttpClient\Client` 与 `ClientFactory`，对接任意 PSR-18 客户端
- `Security\LegacyCipher`，用于读取 1.x 产出的旧密文
- `Support\Type`，承接原全局 `is_*` 系列类型判定
- `Translation\ArrayTranslator` 与 `Translation\Loader\PhpFileLoader`，支持按目录加载语言词条
- `Integration\ThinkPHP\ThinkPHPAdapter` 与 `ExceptionHandler`，提供容器适配与异常映射
- `Config\Environment`、`Http\ServerContext`、`Http\RequestContext`，显式承载环境与请求信息

### 🔧 功能调整

- `Data\Field` 以 `FieldJsonMode`、`FieldTextMode` 承接原 `fieldJson()`、`fieldText()` 的数字模式，`fieldDate()` 改用 `DateGranularity`
- `Security\Hash` 以 `HashLength` 表达摘要长度，`Security\Token` 拆分为签发与校验两步
- `Support\Random` 以 `RandomCharset` 表达字符集，`Text\Uuid` 以 `UuidMode`、`UuidStyle` 表达生成模式与样式
- `Data\Diff` 承接 `updateContrast()`，未发生变化时抛 `SameDataException`
- `Data\Param` 以 `NullPolicy::Blank`、`NullPolicy::StrictNull` 显式表达空值策略
- `Text\Time`、`DateGranularity` 承接时间解析、格式化与毫秒时间戳，`Text\Ip` 承接 IPv4 与整型互转
- `File\Filesystem`、`File\Path`、`File\MediaInfo` 按职责拆分原 `\fxapp\File`
- `Http\Cookie\CookieJar`、`CookieOptions`、`SameSite` 承接 Cookie 读写与选项
- `Support\Dump`、`Support\Formatter`、`Text\Encode`、`Text\Str` 承接原门面中的零散工具方法

### ⚡ 优化改进

- 全仓补齐参数与返回值类型声明，PHPStan level max 无错误
- 以具名枚举取代整型 `type` 参数，并新增工具脚本扫描数字码回流
- SQL 方言收敛为 MySQL 单实现，去掉多方言分支
- 装配改为显式依赖注入，移除静态门面的隐式初始化与全局状态
- 测试覆盖 196 个用例、342 条断言，覆盖配置、参数、SQL、安全、HTTP、文件、翻译与集成

### 🐛 问题修复

- 旧响应数组按下标取值时扩展位缺失导致的越界读取，`Envelope` 固定四字段并提供判定与取值方法
- 旧 `whereSafe()`、`whereFilter()` 依赖字符串白名单防注入的隐患，条件构造改为绑定参数传递
- 旧 `throwable()` 直接终止进程、错误无法被上层捕获的问题，改为抛异常
- `File\Path::join()` 丢弃首段前导斜杠使绝对路径被降级为相对路径，拼接改为保留前导斜杠、盘符与协议头，Linux 下 `Http\View` 可正确定位绝对路径模板

### 📝 文档更新

- 新增 2.0 迁移说明，逐项列出类映射、数字码对照、门面方法对照与不再提供的能力
- README 改写为 Composer 安装与最小装配说明

## 1.6.1

> 2026-10-28 发布

> 2026-10-28 补打标签，未单独发版

> 1.x 自本版起终止维护，正式发版从 2.0.0 开始；升级步骤见升级指南（`docs/upgrade-2.0.md`）。
>
> 本版仅包含 PHP 8.4 兼容修复与文档收尾，无 API 变更，可作为 1.x 最终基线。

### 🐛 问题修复

- 修复 PHP 8.4 下隐式可空参数触发的弃用告警

### 📝 文档更新

- 统一版权信息表述，补充商标说明与 NOTICE 文件

## 1.6.0

> 2024-03-09 发布

> 标签补打于 2026-10-28

### 🔧 功能调整

- HTTP 请求按方法分流，GET 数据经 `\fxapp\Param::url(['type' => '1.2', ...])` 并入查询串，仅 POST、PUT、PATCH、DELETE 才经 `strEncode()` 写入请求体
- 协议识别兼容 `$_SERVER['SCRIPT_URI']`，缺少 `REQUEST_SCHEME` 时从 `SCRIPT_URI` 拆出 scheme，反向代理下不再误判为 http

### 📝 文档更新

- README 项目定位改写为「可以接入任意 PHP 项目，内置诸多实用方法，是一款轻量级 PHP 开发辅助框架」

## 1.5.0

> 2023-12-09 发布

> 标签补打于 2026-10-28

### ✨ 新增功能

- `\fxapp\Base::echo($message, $code = null, $data = [], $extend = [])` 与全局助手 `fxy_echo()`，按统一结构输出响应，为 2.0 `Http\Envelope` 的雏形（2.0 移除）
- `\fxapp\Service::http()` 增加 `$option = []` 请求选项，可透传 curl 参数
- `\fxapp\Safe::rsapem($data)`，为缺少头尾标记的密钥内容补齐 PEM 格式
- 校验方法 `is_time()`、`is_ipv4()`、`is_ipv6()`、`is_url()`、`is_ip()`（2.0 归入 `Support\Type`）

### 🔧 功能调整

- CLI 入参前缀由 `-` 改为 `@`，避免与 PHP 自身选项混淆
- curl 请求默认限定 IPv4，规避双栈环境下 IPv6 不可达导致的超时

### ⚡ 优化改进

- 应用初始化拆分为 load 与 start 两段，加载期不再直接执行业务
- 语言包改为启动时统一加载，避免首次翻译时即时读盘
- 适配 PHP 8，修正 8.0 起收紧的类型与函数签名告警

### 🐛 问题修复

- SQL 条件构造支持写入 null 值，此前 null 会被当作占位符拼接
- 大小写敏感的字段过滤未生效，`whereFilter()` 的 case 参数在部分分支被忽略
- 条件搭建在空值与嵌套数组场景下拼接顺序错乱

## 1.4.0

> 2022-10-17 发布

> 标签补打于 2026-10-28

### 🚨 重大变更

- 通知与第三方服务层整体下线，`notify()`、`third()` 门面与 `app/config/notify.php`、`app/config/third.php` 一并移除（2.0 同样不提供）
- 移除服务驱动 Email、Sms、Push、Flashsms 与第三方驱动 Alibaba、Baidu、Cma、Cup、Sina、Tencent、Tool、Yahoo

### ✨ 新增功能

- `\fxapp\Data::fieldAlias($data, $alias, $delimiter = '.')`，按别名层级重排字段
- `\fxapp\Data::fieldEscape($data, $delimiter = '.')`，批量转义字段值
- 全局助手 `is_function()`，判定回调可调用性
- `\fxapp\Base::load($file, $type = null)`，可按类型加载配置文件

### 🔧 功能调整

- `merge()`、`cover()` 增加 `$cover`、`$type` 参数，合并时可按目标类型转换
- `implode()`、`explode()` 增加 `$unique` 去重，`explode()` 另加 `$limit` 限长，`implode()` 支持 `$explode` 反向拆分
- `timeFormat()` 改为 `timeFormat($time, $param = [])`，格式化规则由参数传入而非固定

## 1.3.0

> 2021-12-27 发布

> 标签补打于 2026-10-28

### ✨ 新增功能

- `\fxapp\Data::whereFilter($value, $method, $case)`，按方法名与大小写拼装过滤条件
- `\fxapp\Data::whereAssemble($data)`，将条件数组拼装为 SQL 片段
- `\fxapp\Data::storeUpperLevel()`、`storeLowerLevel()`，递归取上下级数据
- `\fxapp\Data::fieldDivision($dividend, $divisor)`，按字段做除法并保留精度
- 门面补充 `env()`、`branch()`，读取环境与分支配置
- `app/config/branch.php`、`app/config/facade.php` 两个配置文件
- `\fxapp\Server` 门面补充 URL 设置与获取方法

### 🔧 功能调整

- `fieldJson()`、`fieldText()`、`fieldDate()` 的模式参数统一为 `$param`（原 `$mode`、`$type`），模式位改由数组表达
- `array()`、`object()` 增加 `$filter` 参数，可在转换时过滤键
- `json()` 改为 `json($data, $type, $param)`，编码行为由参数控制
- `\fxapp\Data` 门面大幅调整，条件与字段方法统一走 `whereFilter()` 组装

### 🧹 清理移除

- 删除 `updateCase()`、`updateParam()`，更新条件统一由 `whereAssemble()` 承担

## 1.2.0

> 2020-10-02 发布

> 标签补打于 2026-10-28

### 🚨 重大变更

- 门面方法签名改为变参透传，`config(...$vars)`、`env(...$vars)`、`cookie(...$vars)` 不再固定参数个数

### ✨ 新增功能

- `\fxapp\Data::where($data, $param)` 通用条件构造
- `\fxapp\Data::whereSafe($value, &$method, $case)` 安全条件构造
- `\fxapp\Data::insert()` 插入语句构造
- `\fxapp\Param::paramRepair()`、`paramEmpty($param, $type)` 参数补全与空值判定
- `\fxapp\Text::timeRange()` 时间区间解析
- `\fxapp\Text::strEncode()`、`strEncodeMerge()`、`strDecode()`、`strDecodeMerge()` 编解码
- `\fxapp\File::moveDirectory($oldpath, $newpath)` 目录移动
- `\fxapp\View` 控制器与门面，承接模板渲染
- `frame/Cookie.php`、`app/config/env.php`、`app/store/common.php`
- 全局助手 `fxy_config()`、`fxy_cookie()`、`fxy_env()`、`fxy_dump()`、`fxy_json()`、`fxy_lang()` 与判定 `is_json()`、`is_blank()`
- `\fxapp\Param::url($data)` 与 `\fxapp\View::echo($data, $type = '')`，后者由 `return()` 更名而来

### 🔧 功能调整

- `array()`、`object()` 改为 `array($param, $limit)`、`object($param, $limit)`，可限制转换层级
- `merge($limit, ...$args)`、`cover($args, $limit)` 增补层级控制
- 语言包更名，`lang-core` 改 `base`、`lang-list` 改 `env`、`lang-phrase` 改 `phrase`

## 1.1.0

> 2020-05-24 发布

### 🚨 重大变更

- 全局函数改为类与门面，`app/core/function/` 下 74 个带前缀函数（`dqa_`、`dsc_`、`dso_`、`fcc_`、`fcf_`、`fco_`、`fcs_`、`fmf_`、`fmo_`、`fsi_`、`fss_`、`ftc_`、`fts_`）改为 `app/controller/*` 控制器与 `facade/*` 门面（2.0 移除）
- 目录结构重排，`app/core/` 并入 `app/`，内核迁至 `frame/` 与 `frame/fxyin/`
- 移除 `Cache`、`Cookie`、`Db`、`Debug`、`Exception`、`Log`、`Request`、`Response`、`Session` 内核类，配套缓存、配置、数据库、响应、文件驱动一并删除（2.0 不恢复）
- 移除 `frame/fxyin/vendor/convention.php`、`frame/fxyin/vendor/helper.php` 与 `base.php` 入口，改由 `autoload.php` 与 `\fxyin\App::init()` 启动

### ✨ 新增功能

- `app/controller/` 控制器 `Base`、`Client`、`Data`、`File`、`Math`、`Param`、`Safe`、`Server`、`Service`、`Text`
- 门面层 `app/controller/facade/`，`Data`、`Text`、`Param`、`Safe` 为主要承载
- `frame/App`、`Config`、`Dir`、`Env`、`Facade`、`Lang`、`Loader`、`Service`
- `autoload.php` 自动加载与 `config.php` 全局配置

## 1.0.0

> 2020-05-24 发布

### ✨ 新增功能

- `app/core/function/` 74 个全局函数，按前缀分组（`dqa_`、`dsc_`、`dso_`、`fcc_`、`fcf_`、`fco_`、`fcs_`、`fmf_`、`fmo_`、`fsi_`、`fss_`、`ftc_`、`fts_`）
- `fsi_result()`、`fsi_param()` 统一返回结构与参数取值，`dqa_where()`、`dqa_make()`、`dqa_build()`、`dqa_safe()` 条件构造
- `fcf_json()`、`fcf_crypt()`、`fcf_md()`、`fcf_rsapri()`、`fcf_rsapub()`、`fcf_token()`、`fcf_ipv()` 加解密与令牌
- `fco_thumb()`、`fco_authcode()` 图片缩略与授权码
- `ftc_system()`、`fts_ip()` 系统信息与 IP 归属
- `fmo_merge()`、`fmo_cover()`、`fmo_pick()`、`fmo_append()` 数组处理
- `frame/fxyin/` 内核，含 `App`、`Cache`、`Config`、`Cookie`、`Db`、`Debug`、`Dir`、`Exception`、`File`、`Lang`、`Loader`、`Log`、`Request`、`Response`、`Service`、`Session`（2.0 移除）
- 缓存驱动 `File`、`Redis`，配置驱动 `Ini`、`Json`、`Xml`，数据库驱动 `Mongodb`，响应驱动 `Json`、`Xml`，文件驱动 `Image`
- 通知服务 `Email`、`Sms`、`Push`、`Flashsms` 与第三方服务 `Alibaba`、`Baidu`、`Cma`、`Cup`、`Sina`、`Tencent`、`Tool`、`Yahoo`（1.4 起移除）
- 中英双语语言包与 `app/core/config/app.php`、`notify.php`、`third.php`
- `base.php`、`start.php` 入口文件
