---
title: v1.6.1 能力对等矩阵
outline: [2, 3]
---

# v1.6.1 能力对等矩阵

本页是 v2 对 v1.6.1 的**能力**对等清单，不是 API 兼容清单。v2 保留现代化表现形式，方法名、参数与调用方式可以不同，但 `extend/fxri/base` 与 `extend/fxri/data` 承载的能力必须有对应入口。

机器可读快照见 `tests/Parity/legacy-api.json`；设计决策见 [ADR-0001](./adr/0001-v2-parity)。

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
| Base | 13 | 9 | 4 | 0 |
| Client | 4 | 4 | 0 | 0 |
| Data | 26 | 15 | 9 | 覆盖 2 |
| File | 5 | 5 | 0 | 0 |
| Math | 1 | 1 | 0 | 0 |
| Param | 10 | 7 | 2 | 移除 1 |
| Safe | 6 | 5 | 1 | 0 |
| Server | 6 | 5 | 1 | 0 |
| Service | 2 | 1 | 1 | 0 |
| Text | 18 | 9 | 9 | 0 |
| View | 1 | 0 | 1 | 0 |

需新增项为零：`App::run` 已由可选 `Application\Router` 承接，其余能力均已落到 v2 入口——语言、文件加载、数据层级/字段/参数、参数转换、PEM 规范化、分支上下文、HTTP 下载、文本时间/编码、XML 响应与目录创建。

完整映射见 `tests/Parity/legacy-api.json`。

## 四、全局函数

18 个 `is_*` / `fxy_*` 均有 v2 等价入口，按设计不再提供全局函数：

- `is_*` → `Support\Type`
- `fxy_lang` / `fxy_config` / `fxy_cookie` / `fxy_env` / `fxy_dump` / `fxy_json` / `fxy_echo` → `Language`、`Config`、`CookieJar`、`ServerContext`、`Dump`、`Json`、`Envelope`

## 五、运行时

| 1.x | v2 目标 | 状态 |
| --- | --- | --- |
| `App::init` | `Application\Bootstrap` | 等价 |
| `App::run` | `Application\Router`（可选） | 等价 |
| `Config::load/has/get` | `ConfigLoader`、`Config` | 等价 |
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

## 七、设计上不支持

以下不具备直接等价接口，但能力有替代：

- `whereSafe`、`whereFilter`：由 `WhereBuilder` 绑定参数覆盖。
- 门面与全局函数：由 `Fxri\*`、`Application\Services` 覆盖。
- 配置驱动的六元响应：由 `Envelope` 与项目响应工厂覆盖。

## 八、相关文档

- [ADR-0001：v2 对等架构](./adr/0001-v2-parity)
- [1.x 升级 2.0 指南](./upgrade-2.0)
- [2.0 API 对照](./migration-2.0)
