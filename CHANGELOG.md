# 风音框架

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

### 📝 文档更新

- 新增 2.0 迁移说明，逐项列出类映射、数字码对照、门面方法对照与不再提供的能力
- README 改写为 Composer 安装与最小装配说明
