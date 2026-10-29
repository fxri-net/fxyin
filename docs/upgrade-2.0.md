---
title: 1.x 升级 2.0 指南
outline: [2, 3]
---

# 1.x 升级 2.0 指南

2.0 是一次不保留 API 兼容层的大版本改版：门面层与全局函数移除，点分模式号改为具名枚举，六元响应数组改为固定五键信封，安装方式改为 Composer。本指南面向仍在使用 1.x 的项目，给出从体检、选路到验收、回滚的可执行步骤。

> 适用范围：1.0.0 至 1.6.1。1.x 仅保留历史标签，不再发版；正式发版从 2.0.0 开始。

## 一、版本与支持策略

| 版本 | 状态 | 说明 |
| --- | --- | --- |
| 1.0.0–1.6.1 | 历史基线 | 只保留标签与更新日志，不再接收修复与新特性 |
| 2.0.0 起 | 正式发版 | Composer 包 `fxri/fxyin`，要求 PHP 8.2 起 |

- `v1.6.1` 是对 1.x 终点的标签标记，包含 PHP 8.4 兼容修复，未单独发版。
- 1.x 的兼容层不会出现在 2.x 中；需要过渡时由接入方项目自行保留一层薄适配，见[扩展目录迁移](./upgrade-2.0-extend)。
- 响应信封固定为 `code`、`message`、`data`、`extend`、`debug` 五个字段，`code` 为整数业务码（成功缺省 200），成败由内部 `success` 判断而不对外输出；分页接口的 `data` 用 `Data\Page` 的四键壳。

### 2.1.0 能力补齐

2.1.0 在不恢复门面的前提下补齐了 1.6.1 的对外能力，升级时优先使用下列 v2 入口：

| 1.x 能力 | v2 入口 |
| --- | --- |
| `Client::param` | `Http\RequestInput` |
| `Param::init` | `Application\Cli` + `Bootstrap::withCliArguments()` |
| `File::getList` | `File\Filesystem::findFiles()` |
| `File::getMediaInfo` | `File\VideoInfo::inspect()` |
| `Service::http` / `httpDown` | `Http\HttpClient\Curl` / `Curl::download()` |
| `Safe::token` | `Token`（新）/ `LegacyToken`（旧） |
| `Safe::rsapri` / `rsapub` | `Rsa::privateEncrypt()` / `publicDecrypt()` |
| `Text::timeFormat` / `timeMilli` | `Time::duration()` / `milliFormat()` / `milliParse()` |
| `Text::timeChange` / `timeRange` / `convert` | `TimeOutput` / `TimeRangeOptions` / `EncodeConvert` 枚举与值对象 |
| `Text::uuid` 格式化现有串 | `Uuid::format()` |
| `Base::lang` 标记与首字母 | `Language::phrase(..., true)` |
| `Param::object` / `append` | `Cast::emptyToObject()` / `Merger::append()` |
| `Env::*` / `fxy_env` | `Config\Store`（`env` 命名空间） |

完整映射见[对等矩阵](./parity-1.6.1)与[API 对照](./migration-2.0)。

## 二、升级前必须确认的四件事

1. **PHP 版本**：实际部署版本必须达到 8.2。`composer.json` 里的 `php` 约束可能已经过时，以线上运行版本为准。
2. **Composer 可用**：2.0 只通过 Composer 分发。没有 `composer.json` 的项目需要先补接入，见[接入形态手册](./upgrade-2.0-runtime)。
3. **可回退**：升级前保留 1.x 目录、部署包与数据库快照；升级过程中每一步都应能切回旧引导。
4. **框架副本可复原**：`extend/fxri/base` 是随项目分发的副本，普遍被就地改过，未必等于任何正式标签。升级前先把它与手头的 1.x 正式副本或备份逐文件比对，导出「本地改动清单」并整目录备份；清单里凡 1.6.1 没有的方法或行为，都要先定落点再删除。

```bash
# 任选其一：与 1.x 备份、正式发布包或另一份干净副本比对
diff -r backup/extend/fxri/base project/extend/fxri/base
```

> 只比对 `config.php`、`app`、`frame`、`autoload.php` 即可覆盖全部框架代码；`data` 目录属项目扩展，按[扩展目录迁移](./upgrade-2.0-extend)单独处理。

## 三、项目自评

先回答五个问题，再选路线：

| 维度 | 取值 | 影响 |
| --- | --- | --- |
| PHP 实际版本 | 低于 8.2 / 8.2 及以上 | 低于 8.2 时先做 PHP 升级，不与框架升级混在一起 |
| Composer | 有 / 无 | 无 Composer 需先补 `composer.json` 与自动加载 |
| 宿主框架 | ThinkPHP 6 / ThinkPHP 5 / 非 ThinkPHP | 决定引导与异常映射模板 |
| 扩展目录 | 仅 `data` / 含其他模块 | `extend/fxri/safe` 等属于独立工具，不并入本次迁移 |
| 旧 API 调用量 | 小于 100 / 100–500 / 大于 500 | 决定走直接改造还是过渡层收敛 |

### 体检命令

体检工具随仓库分发，不进入 Composer 安装包。先检出框架仓库，再对目标项目只读运行：

```bash
git clone https://github.com/fxri-net/fxyin.git
cd fxyin

# 项目画像：PHP、宿主框架、入口、扩展模块、调用量档位与阻塞项
php tools/upgrade-inspect.php profile --root=/path/to/project --markdown

# 旧 API 统计：按目录与能力域给出替换建议
php tools/upgrade-inspect.php scan --root=/path/to/project --markdown

# 生成只减不增的基线文件
php tools/upgrade-inspect.php baseline --root=/path/to/project --write --baseline=/path/to/project/upgrade-baseline.json
```

工具只读，不会 `include` 项目文件，也不会执行 Composer。

## 四、路线选择

```text
PHP < 8.2 ─────────────── 是 → 先完成 PHP 升级，再回到本指南
没有 Composer ─────────── 是 → 先补 composer.json 与自动加载
旧 API 小于 100 处 ────── 是 → 路线 S：直接改造
旧 API 100–500 处 ─────── 是 → 路线 M：过渡门面 + 分域替换
旧 API 大于 500 处 ────── 是 → 路线 L：过渡门面 + 基线收敛 + 双跑
```

| 路线 | 适用 | 做法 | 退出条件 |
| --- | --- | --- | --- |
| S 直接改造 | 调用量小、扩展目录薄 | 删除 `extend/fxri`，接 Composer 2.0，逐文件替换旧调用 | 全仓 `\fxapp\` 与点分模式号归零 |
| M 过渡门面 | 中等体量、需要边改边跑 | 项目内保留 `\fxapp\` 命名空间做适配层，内部改调 `Fxri\*`，按能力域替换 | 适配层调用量持续下降并归零 |
| L 过渡门面 + 基线 | 大项目、多人并行 | 在 M 的基础上增加按目录基线，超限即失败；关键接口新旧响应双跑比对 | 基线归零、双跑差异清零 |

路线只是迁移手段，终点都是删除过渡层，业务代码直接使用 `Fxri\*` 与 `Kernel`。

## 五、八个阶段

| 阶段 | 目标 | 退出条件 |
| --- | --- | --- |
| 0 体检与基线 | 生成项目画像、统计旧 API、记录基线 | 体检报告与基线文件入库 |
| 1 环境与依赖 | PHP 8.2、Composer、`fxri/fxyin:^2.0` | `composer install` 成功，自动加载可用 |
| 2 引导与装配 | 删除 `extend/fxri/base`，改用 `KernelBuilder` | 入口可启动，`think list` 或等价冒烟通过 |
| 3 配置 | `data/app/config` 拆成纯数组与运行时服务 | 配置加载不再调用旧门面或读取超全局变量 |
| 4 扩展目录 | 处理 `facade_ext`、`oracle`、`store`、语言与函数 | 扩展目录只剩项目自有代码，旧注册项清零 |
| 5 代码分域 | 按配置、参数、SQL、数据、文本、文件、安全、HTTP 逐域替换 | 各域基线归零 |
| 6 响应与异常 | 内部用 `Envelope`，对外保持旧协议 | 新旧响应双跑一致，异常映射可用 |
| 7 验收与清理 | 删除过渡层、`\fxapp\` 命名空间与旧目录 | 全量验收通过，回滚路径演练完成 |

各阶段的接入模板、扩展目录细节与检查项分别见下面五篇文档。

## 六、五篇文档

| 文档 | 内容 |
| --- | --- |
| [接入形态手册](./upgrade-2.0-runtime) | ThinkPHP 6、ThinkPHP 5、非 ThinkPHP、无 Composer 四类引导模板 |
| [扩展目录迁移](./upgrade-2.0-extend) | `extend/fxri/base` 与 `extend/fxri/data` 的逐项对照 |
| [2.0 API 对照](./migration-2.0) | 类映射、点分码、门面方法与无等价能力 |
| [升级检查清单](./upgrade-2.0-checklist) | 阶段 0–7 的可复制清单与基线模板 |
| [快速开始](./getting-started) | 2.0 的最小安装与装配 |

## 七、回滚

- 升级前为 1.x 目录与部署包打快照；入口保留切换开关，必要时切回旧 `bootstrap.php`。
- 数据库与旧密文不需要回滚：2.0 的 `Security\LegacyCipher` 只解密，不重写数据。
- 对外响应协议单独排期；框架升级不回滚协议变更，避免把两件事绑在一起。
- Composer 约束可从 `^2.0` 退回 `^1.6`；1.x 标签仍在，可重新检出。

## 八、红线

1. 不把 PHP 升级与框架升级放进同一次变更。
2. 不同时保留 1.x `autoload.php` 与 Composer 自动加载，避免两套加载器互相遮蔽。
3. 不在框架升级的同时修改对外响应协议。
4. 不把 `extend/fxri/safe` 等独立模块计入 fxyin 迁移范围。
5. 不在多个接入方仓库之间批量复制改动，先在小项目验证再推广。
