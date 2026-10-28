---
title: ADR-0001：v2 对等架构
---

# ADR-0001：v2 对 1.6.1 的能力对等架构

- 状态：已接受
- 日期：2026-10-28

## 背景

v1.6.1 通过 `extend/fxri/base` 与 `extend/fxri/data` 提供框架运行时、默认配置、语言包、门面 API、全局函数、应用启动与项目扩展机制。v2 已改为 Composer + `Kernel` + `Fxri\*`，但部分基础能力（语言包、默认配置、应用启动、项目服务注册）尚无上位替代。

需要在不恢复 1.x 门面与全局函数的前提下，让 v2 具备同等的**能力**，表现层使用 v2 形式。

## 决策

采用「资源 + 服务 + 应用层」三段式：

1. **资源层**：`resources/config`、`resources/language` 随包分发，默认加载。
2. **服务层**：
   - 语言拆为 `Catalog`（数据）、`Translator`（字符串翻译）、`LocaleResolver`（请求检测）、`Language`（组合入口）。
   - 配置拆为只读 `Config`、可变 `Config\Store`、加载器 `Config\Loader`。
   - 缺失能力放到语义正确的 v2 类或新服务，不复制 1.x 类名。
3. **应用层**：
   - `Application\Bootstrap` 替代 `App::init()` 的目录扫描与启动钩子。
   - `Application\Services` 替代 `facade_ext`、`oracle` 与 `data/config.php` 注册。
   - `Application\Router` 作为可选模块替代 `App::run()`，默认不启用。

## 理由

- 不把嵌套语言包与旧占位符塞进 `ArrayTranslator`，避免违反 Symfony `TranslatorInterface` 的字符串契约。
- 不把运行期 `Config::set` 塞回只读 `Config`，保持内核不可变。
- 不用门面魔法恢复 `facade_ext`，避免重新引入 2.0 已移除的全局状态与静态转发。
- 按矩阵补能力，而不是按类名补旧 API，避免得到混合架构。

## 后果

- `2.1.0` 起默认加载内置配置与语言包；可通过 `bare()`、`withoutDefaultConfig()`、`withoutBuiltinLanguage()` 关闭。这是行为变化，更新日志单列。
- 新增 `resources` 随包分发，发布包卫生检查需覆盖。
- 需要一套对等测试与矩阵审计，证明 1.6.1 的 92 个门面方法、18 个全局函数、27 个运行时方法与资源均有映射或明确状态。
- 路由与启动不默认接管，保持 v2 定位。

## 备选方案

- **兼容层**：恢复 `\fxapp\*`、全局函数与 `App` 静态入口。否决：与 2.0 定位冲突，且会把 1.x 状态问题带回。
- **增量补丁**：直接把旧方法加到现有 v2 类。否决：语言、配置、扩展机制都会形成混合结构，长期维护成本更高。
- **两代并存**：v1.6.1 继续完整，v2 不补齐。否决：不满足「v1.6.1 能力都要有上位替代」的目标。
