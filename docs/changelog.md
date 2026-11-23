---
outline: [2, 2]
---

# 更新日志

> 完整变更历史以根 `CHANGELOG.md` 为准，本页由 `toolkit release mirror` 自动同步，请勿手改。

历史版本：[1.x](./changelog/1.x)

## 2.2.8

> 2026-11-23 发布

### 🐛 问题修复

- `Envelope::toArray()` 的 `extend` / `debug` 统一为具名映射，空值输出 `{}`、列表归入保留键 `items`，不再出现对象 / 数组翻转与数字键
- `extend()` / `debug()` 仍返回数组，PHP 侧取值方式不变

### 📝 文档更新

- ADR-0001 页面标题与一级标题统一为「v2 对 1.6.1 的能力对等架构」
- `docs/http.md`、`docs/http-api.md` 与 `fxri-fxyin` 技能同步对象与 `items` 口径

## 2.2.7

> 2026-11-13 发布

### ⚡ 优化改进

- 文档站导航与侧栏抽为 `docs/site-nav.json` 单一数据源，站点配置只做引用展开
- 导航、主题与产物校验改由工具命令执行，删除三份本地校验脚本
- `docs/.vitepress/tsconfig.json` 开启 `resolveJsonModule`，支持配置导入 JSON

## 2.2.6

> 2026-11-06 发布

### ✨ 新增功能

- `sync:version` 脚本，`changelog:version` 升版本后自动回写 `Package::VERSION`，发版不再人工补版本

## 2.2.5

> 2026-11-06 发布

### ✨ 新增功能

- 文档站 404 兜底页与导航规范校验
- 门禁补依赖安全公告检查（`composer audit`）

### 🔧 功能调整

- `migration-2.0.md` 拆为 API 对照与行为变更两页，升级页标题统一去掉「升级 2.0：」前缀

### ⚡ 优化改进

- 文档站校验补 404 两条断言（`search: false`、`sitemap.transformItems`），并在构建后核验产物
- CI 补 PR 专用文档站作业，部署链路在构建后断言产物，产物检查不再只存在于本地
- 404 页加 `search: false`，不进入站内搜索索引
- 升级相关页面统一迁入 `upgrade/` 目录，顶栏收敛为 5 项、侧栏重排为 5 组，参考内容按域嵌套
- 404 页从站点地图过滤，避免被搜索引擎当正常页面收录
- 升级文档按主题拆为 API 对照、数字码对照、门面方法对照、扩展迁移四页，单页不再超过 300 行
- HTTP 页拆出 HTTP API 参考页，侧栏「升级」拆为「升级」与「迁移」两组，顶栏下拉补新页入口
- `pnpm verify` 改为并发执行各验收步骤，输出按声明顺序回放并打印每步耗时

### 🐛 问题修复

- 产物断言改为从 `404.md` 读取一级标题比对搜索索引，避免改标题后检查静默失效

## 2.2.4

> 2026-11-03 发布

### ⚡ 优化改进

- 统一 package.json 元数据（author、repository、homepage、keywords 与字段顺序）
- toolkit 相关脚本改为显式探测全局命令，缺失时跳过

## 2.2.3

> 2026-11-03 发布

### ✨ 新增功能

- `AGENTS.md` 补规则更新时间锚点与刷新纪律，便于判断规则快照是否失同步

### ⚡ 优化改进

- CHANGELOG 文档镜像改用全局 `toolkit changelog doc`，删除本地重复脚本
- 验收门禁在未安装全局 toolkit 时跳过镜像检查，远程 CI 不依赖该工具
- 文档站顶部栏统一为「FAQ → 更新日志」，答疑下拉中的常见问题提升为独立 FAQ 入口
- 文档站主题升到 1.1，渐变改为覆盖整页
- 文档站主题升到 2.1，改为中性底 + 单站强调色

## 2.2.2

> 2026-10-31 发布

### 🔧 功能调整

- `Text\Time::change()` 无法解析输入时改为抛 `InvalidArgumentException`，与同页 `parse()` 及文本层「不合法即失败」的口径一致，不再返回 `null`
- `Time::relative()` 的 `now` 不是整数时抛 `InvalidArgumentException`，不再静默回落到当前时间
- `Translation\ArrayTranslator` 的占位符规则与 `Translator::format()` 统一，支持 `{:name}` 与 `sprintf` 数字参数
- `Http\HttpClient\Client::download()` 去掉响应体 UTF-8 BOM 与 `Curl` 对齐；响应缺少状态码或正文方法时抛 `RuntimeException`，不再按 200 放行
- `fxyin` 入口只分发当前安装可用的命令域，接入项目执行本仓专用命令时以「命令不可用」退出 2

### ⚡ 优化改进

- 技能 `SKILL.md` 的 composer 脚本示例改为指向 `references/ide.md`，不再与技能参考页重复
- 技能 `references/config.md` 删去与加载规则表重复的条目

### 🐛 问题修复

- `Time::change()` 在截断结果为 `0` 时被误判为失败返回 `null`
- `Time::range()` 把逗号串里的空项当作有效值，起止配置的默认值不生效

### 📝 文档更新

- 技能入口薄壳的注释改为指向 `php vendor/bin/fxyin skills`，删除后按提示执行该命令即可补齐
- `composer meta`、`composer meta:check` 的说明补上「接入项目自行声明」限定语，避免误认为本包自带脚本
- `fxri-fxyin` 技能补充上述限定语，并修正 `openssl` 扩展口径为必需；版本升至 1.1.2

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

### 🔧 功能调整

- 升级体检工具与对等矩阵快照随包分发，接入项目可直接运行 `vendor/bin/fxyin upgrade`
- `composer.json` 声明 `bin`，`scan`、`package`、`parity`、`upgrade:inspect` 脚本改走统一入口
- 两个 AI 技能的许可证改为 `Apache-2.0`，与本包一致
- `fxri-fxyin` 技能补充命令行用法（`references/cli.md`），版本升至 1.1.0
- `fxri-fxyin-upgrade` 技能命令改为 `vendor/bin/fxyin upgrade`，版本升至 1.0.2

### ⚡ 优化改进

- `psr/log` 约束放宽为 `^1.1 || ^2.0 || ^3.0`，可与锁定 1.x 的宿主框架共存
- 发布归档排除本地安装产物、文档站工具链与协作元数据，分发包不再携带 `node_modules`、`vendor` 与协作规则文件
- 技能版本在 frontmatter 与正文首部双写，便于核对是否同步

## 2.1.2

> 2026-10-29 发布

> 本次发布包与 2.1.1 的差异只有版本号常量与本段更新日志，无任何行为变更；升级体检工具与 1.x 升级文档随仓库提供，不进分发包。

### ⚡ 优化改进

- 升级体检工具按项目配置解析应用目录、命名空间、扩展目录与全局函数，不再写死默认目录
- 升级体检工具识别框架副本内的本地新增方法，并区分手写路由入口与风音 1.x 原生入口
- 1.x 升级文档补充框架副本本地改动盘点、本地新增方法落点、ThinkPHP 8 接入与手写路由入口处理

### 🐛 问题修复

- 已发布版本标签重新指回发布记录对应的提交，标签谱系与包仓库记录恢复一致，`git describe` 不再回落到 2.0.0

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
