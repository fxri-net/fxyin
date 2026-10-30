---
title: v1.6.1 能力对等矩阵
outline: [2, 3]
---

# v1.6.1 能力对等矩阵

本页是 v2 对 v1.6.1 的**能力**对等清单，不是 API 兼容清单。v2 保留现代化表现形式，方法名、参数与调用方式可以不同，但 `extend/fxri/base` 与 `extend/fxri/data` 承载的能力必须有对应入口。

机器可读快照见 `resources/parity/legacy-api.json`；设计决策见 [ADR-0001](./adr/0001-v2-parity)。

## 一、总量

| 项 | 数量 |
| --- | --- |
| 门面方法 | 92 |
| 全局函数 | 18 |
| 运行时方法 | 27 |
| 内置语言包 | 6 |
| 默认配置文件 | 4 |

状态说明：

- `等价`：v2 已有入口，行为可按 v1.6.1 对齐。
- `需新增`：v2 尚无入口，计划新增。
- `覆盖`：由更安全的等价能力覆盖，不提供旧接口。
- `设计移除`：2.0 主动移除，能力由 v2 其他入口承接。

## 二、`base` 与 `data` 目录职责

| 1.x 位置 | 职责 | v2 上位替代 |
| --- | --- | --- |
| `base/autoload.php`、`Loader` | 自动加载与配置播种 | Composer PSR-4、`KernelBuilder` |
| `base/frame/App` | 应用加载、启动、路由 | `Application\Bootstrap`；路由由可选 `Application\Router` 承接 |
| `base/frame/Config` | 配置读取与运行期写入 | `Config`、`Config\Store` |
| `base/frame/Lang` | 语言包、检测、Cookie、语言列表 | `Translation\Catalog`、`Translator`、`LocaleResolver`、`Language` |
| `base/frame/Env` | 运行期环境叠加与请求环境 | 叠加层由 `Config\Store`（`env` 命名空间）与 `File\Loader` 承接；请求环境见 `Http\ServerContext` |
| `base/frame/Cookie` | Cookie 读写 | `Http\Cookie\CookieJar` |
| `base/frame/Dir` | 目录与路径 | `File\Filesystem`、`File\Path` |
| `base/frame/Facade`、`Loader` | 门面与自动加载 | 移除；由 `Application\Services` 与 Composer 替代 |
| `base/app/config` | 默认配置 | `resources/config`，默认加载 |
| `base/app/language` | 内置中英文语言包 | `resources/language`，默认加载 |
| `base/app/function` | 全局函数 | `Support\Type` 与各服务 |
| `base/app/store` | 启动钩子 | `Application\Bootstrap` 生命周期钩子 |
| `base/app/controller`、`facade` | 门面 API | `Fxri\*` 类与服务 |
| `data/config.php` | 项目扩展注册 | `Application\Services`、`Application\Module` |
| `data/app/config` | 项目配置覆盖 | `KernelBuilder::withConfigDir()` |
| `data/app/language` | 项目语言覆盖 | `KernelBuilder::withLanguageDir()` |
| `data/app/function` | 项目函数 | Composer `autoload.files` 或 `Bootstrap::withFunctions()` |
| `data/app/store` | 项目启动钩子 | `Application\Bootstrap` 钩子 |
| `data/app/controller`、`facade_ext` | 项目控制器与扩展方法 | 项目服务类 + `Application\Services` |
| `data/app/controller/oracle` | 服务定位器 | `Application\Services` 或宿主容器 |
| `data/app/controller/think/Exception` | ThinkPHP 异常映射 | `Integration\ThinkPHP\ExceptionHandler` |

## 三、门面方法

| 门面 | 方法数 | 等价 | 需新增 | 覆盖 / 移除 |
| --- | --- | --- | --- | --- |
| Base | 13 | 13 | 0 | 0 |
| Client | 4 | 4 | 0 | 0 |
| Data | 26 | 22 | 0 | 覆盖 4 |
| File | 5 | 4 | 0 | 覆盖 1 |
| Math | 1 | 1 | 0 | 0 |
| Param | 10 | 10 | 0 | 0 |
| Safe | 6 | 6 | 0 | 0 |
| Server | 6 | 4 | 0 | 覆盖 2 |
| Service | 2 | 2 | 0 | 0 |
| Text | 18 | 18 | 0 | 0 |
| View | 1 | 1 | 0 | 0 |

需新增项为零：全部门面能力已落到 v2 入口。`App::run` 由可选 `Application\Router` 承接；`Client::param` 由 `Http\RequestInput` 承接；`File::getList` 由 `Filesystem::findFiles`、`File::getMediaInfo` 由 `File\VideoInfo::inspect` 承接；`Param::init` 由 `Application\Cli` 承接，`Param::object` / `append` / `pick` 分别由 `Cast::emptyToObject`、`Merger::append`、`Picker::pick` 承接；`Safe::rsapri/rsapub` 由 `Rsa::privateEncrypt/publicDecrypt` 承接，`Safe::token` 由 `Token` 与 `DesToken` 承接；`Service::http/httpDown` 由 `HttpClient\Curl` 承接；`Text` 的嵌套编解码、长度校验、时长分解、毫秒转换与标识串格式化分别落到 `Encode::encodeAll/decodeAll`、`Str::isLength`、`Time::duration`、`Time::milliFormat/milliParse`、`Uuid::format`；语言直通/忽略标记与首字母大写由 `Language::phrase` 承接。

完整映射见 `resources/parity/legacy-api.json`。

## 四、全局函数

18 个 `is_*` / `fxy_*` 均有 v2 等价入口，按设计不再提供全局函数：

- `is_*` → `Support\Type`
- `fxy_lang` / `fxy_config` / `fxy_cookie` / `fxy_env` / `fxy_dump` / `fxy_json` / `fxy_echo` → `Language`、`Config`、`CookieJar`、`ServerContext`、`Dump`、`Json`、`Envelope`

## 五、运行时

| 1.x | v2 目标 | 状态 |
| --- | --- | --- |
| `App::init` | `Application\Bootstrap`（按路径装配；`env.app` 名称映射不迁移） | 等价 |
| `App::run` | `Application\Router`（可选） | 等价 |
| `Config::load/has/get` | `Config\ConfigLoader`（目录）、`Config`；单文件用 `File\Loader + Config\Store::set` | 等价 |
| `Config::set` | `Config\Store::set` | 等价 |
| `Lang::range/set/load/has/get` | `Translation\Catalog`、`Language` | 等价 |
| `Lang::detect`、Cookie/列表配置 | `Translation\LocaleResolver` | 等价 |
| `Cookie::get/set` | `CookieJar` | 等价 |
| `Dir::create` | `Filesystem::createDirectory` | 等价 |
| `Env::init/load/has/get/set` | `Config\Store`（`env` 命名空间）、`File\Loader` | 等价 |
| `Param::init` | `Application\Cli`、`Bootstrap::withCliArguments()` | 等价 |
| `Server::env` | `Application\Cli::serverDefaults()`、`RequestContext::fromGlobals()` | 等价 |
| `Loader::*`、`Facade::__callStatic` | Composer、`Application\Services` | 设计移除 |

## 六、资源

- 语言包 6 份：`zh-cn`/`en-us` × `base`/`env`/`phrase`，全部随包提供。
- 默认配置 4 份：`app`、`branch`、`env`、`facade`；v2 迁入 `app`、`branch`、`env` 三份，`facade` 的 1.x 响应/条件配置不进入 v2 默认值。
- `env` 只保留纯数据 `view.lang`；v1 `env.app` / `env.base` / `env.facade` 的运行期快照分别由 `Application\Bootstrap` 目录装配、`Http\RequestContext` 与 `Application\Services` 承接，不再写入默认配置。

## 七、设计上不支持

以下不具备直接等价接口，但能力有替代：

- `whereSafe`、`whereFilter`：由 `WhereBuilder` 绑定参数覆盖。
- `storeUpperLevel`、`storeLowerLevel`：由 `Data\Hierarchy::ancestors()` / `descendants()` 在应用层递归，替代 MySQL 专有递归 SQL。
- `File::moveDirectory`：v2 用 `rename()` 整目录移动，不再把源目录内容扁平合并进目标目录。
- `Safe::crypt`、`Base::crypt`：任意 openssl 算法与参数由 `Cipher`（固定 AES-256-GCM）与 `DesCipher`（仅 DES 读写）承接，不再暴露算法选择。
- `Server::ip`：由 `HttpClient\Curl` + `Param\Json` 组合取公网地址。
- `Server::system`：由 PHP 原生 `PHP_OS_FAMILY` 承接，不再做版本级名称映射。
- 门面与全局函数：由 `Fxri\*`、`Application\Services` 覆盖。
- 配置驱动的六元响应：由 `Envelope` 的五键信封覆盖（`code`、`message`、`data`、`extend`、`debug`）。

### 7.1 行为差异清单

以下差异是 2.0 的既定设计或已确认的取舍，能力均有替代：

| 项 | 1.6.1 | v2 |
| --- | --- | --- |
| 响应结构 | 配置驱动 6 元数组 | `Envelope` 固定五字段 |
| 错误模型 | `throwable()` 直接终止进程 | 抛异常，由接入层映射 |
| 对称加密 | 任意 openssl 算法/口令/IV | `Cipher` 固定 AES-256-GCM；旧数据 `DesCipher` |
| `Client::ip` | 无条件信任转发头 | 默认只信直连，转发需可信代理 |
| 浏览器/系统名称 | IE8、Firefox3 等旧标签 | 现代标签集合（Edge 等） |
| `File::formatSize` | 最大 TB | 增加 PB |
| `Uuid` 默认 | 大写；种子直接 MD5 | 小写；种子一律 MD5；现有串用 `Uuid::format()` |
| `File::moveDirectory` | 内容扁平合并 | `rename` 整目录 |
| `Server::branch` | 命中后自动写回配置 | 只解析分支，配置由调用方写入 `Config\Store` |
| `Base::json` / `fxy_json` | 已编码串不重复编码；非 JSON 串按查询串解码 | `Json` 只处理 JSON；先 `Type::isJson()`，查询串用 `Encode::decodeAll()` |
| `Language::phrase(null)` | 返回字符串 `null` | 返回全部词条 |
| `Param::array` / `merge` | 保留 `''` 标记；`$type` 强转 | 纯转换/合并；类型由调用方显式转换 |
| `Param::url` | 类型 `1.1/1.2/2.1` | `Url::build/query/withQuery` 组合 |
| `Data::htmlFilter` | 可传 `$flags` | 固定 `ENT_NOQUOTES` 语义 |
| `Text::implode/explode` | 接受数字、对象等混合值 | 只接受字符串/数组 |
| `is_function` / `is_email` | 仅对象 callable；正则邮箱 | 任意 callable；`filter_var` |
| `Math::rand` | 数字类型排除 `0` | 包含 `0` |
| `App::init($name)` | 按 `env.app` 名称选目录 | 按应用路径显式 `Bootstrap::make($path)` |
| `Config::load($file,$name)` | 单文件按名称挂载 | `File\Loader::read` + `Config\Store::set` |
| `File::getMediaInfo` | 自动读取配置命令 | 显式传命令；`inspectTemplate()` 承接旧模板 |
| `Service::http` GET 字符串数据 | 拼进查询串 | 只拼数组数据 |
| `Param::init` | 从 `$_SERVER['argv']` 移除已消费参数 | `Cli::parse()` 返回剩余参数，不改写全局；由调用方继续传递 |
| `Server::env` | 改写 `$_SERVER` 的 `SERVER_NAME` / `REQUEST_URI` | 只写 `env.base.host/uri`，请求上下文通过 `RequestContext::fromGlobals($defaults)` 注入 |
| `Client::param` 方法筛选 | `$method` 字符串按 `get/post/input/cli` 组合 | `RequestInput` 分源方法 + `all()` / `only()` 组合 |
| `Text::timeFormat` 单位标签 | 语言包键 `millisecond/sec/...` | `Time::duration()` 默认中文标签，可用 `$labels` 覆盖 |
| `File::formatSize` | 形如 `1.5MB`（无空格） | `Formatter::size()` 形如 `1.50 MB`，带千分位；单位增加 PB |
| `Text::strlen` | 返回响应数组，含错误码 | `Str::isLength()` 返回布尔值 |
| `Text::implode` / `explode` | 带 `$explode` 标志与 `$limit` 参数 | `Str::implode()` / `Str::explode()`；返回数组用 `Str::explode()`，限长用 `array_slice()` |
| `Text::splice` | 无条件追加 | `Str::splice()` 默认去重；无条件追加传 `$unique = false` |
| 查询串编码 | `urlencode`，空格为 `+` | `Encode::encodeAll()` 按 RFC 3986，空格为 `%20` |
| `Service::httpDown` 失败 | 返回 `false` | `Curl::download()` 非 2xx 抛异常 |
| `Data::updateContrast` 数据相同 | 返回「数据相同」响应数组 | `Diff` 抛 `SameDataException` |
| `Data::whereBuild` | 内置 `find_in_set`、`json array/object`、`field`、`like fuzzy` | v2 只内置通用比较；MySQL 专有条件用 `Expression` 或项目数据库层 |
| `Base::ipv4` / `Safe::rsapem` / `Text::letters` 等 | 非法输入返回 `false` | 改为抛异常或类型化参数 |

## 八、相关文档

- [ADR-0001：v2 对等架构](./adr/0001-v2-parity)
- [1.x 升级 2.0 指南](./upgrade-2.0)
- [2.0 API 对照](./migration-2.0)
