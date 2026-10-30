---
name: fxri-fxyin-upgrade
description: 风音框架 1.x 升级 2.x 的迁移指引：体检画像、旧 API 统计、只减不增基线、S/M/L 路线选择、八个阶段与验收回滚，以及门面与 Param/Data/Text/Client/Server 的旧新写法映射。用户提到 1.x 升级 2.0、升级风音框架、迁移到 fxri/fxyin、门面移除、\fxapp\、\fxyin\、fxy_echo、fxy_config、fxy_env、点分模式号、六元响应数组、extend/fxri/base、extend/fxri/data、对等矩阵、基线、baseline、旧 API 扫描时使用；英文说法如 upgrade fxyin、migrate 1.x to 2.x、legacy facade、baseline check 同样适用。不用于升级后的日常接入与响应配置细节（走 fxri-fxyin），不用于业务项目的迁移取舍或发版流程。
license: Apache-2.0
metadata:
  version: "1.0.2"
  author: fxri
  source: https://github.com/fxri-net/fxyin
---

# 1.x 升级 2.x

> 本技能版本 1.0.2（随 fxri/fxyin 同批分发）。
>
> 本技能只承载框架通用的迁移步骤与对照关系。升级后的响应结构、配置加载写法统一指回 `fxri-fxyin` 技能与仓库 `docs/`，此处不重复。业务项目的迁移优先级、过渡层去留、对外协议变更排期属于项目自身决策，以项目任务记录为准。

## 何时使用

- 项目仍在 1.x，要评估改动量与路线。
- 已经决定升级，需要按阶段推进并设置门禁。
- 看到旧写法（门面、全局函数、点分模式号、六元响应数组），要查对应新写法。
- 需要为多人并行迁移设置「只减不增」的基线。

## 决策树

```text
现在该做什么？
├─ 还没体检、不知道体量        → references/inspect.md（profile / scan / baseline）
├─ 已知体量，要选路线与排阶段  → references/stages.md（S/M/L 与阶段 0–7）
├─ 手上有旧调用要改写          → references/mapping.md（门面与分域映射）
├─ 问响应信封怎么组、分页壳   → fxri-fxyin 的 references/response.md
└─ 问配置目录怎么拆、怎么叠加 → fxri-fxyin 的 references/config.md
```

判断依据与逐条说明以仓库文档为准：总览 `docs/upgrade-2.0.md`，清单 `docs/upgrade-2.0-checklist.md`，API 对照 `docs/migration-2.0.md`，对等矩阵 `docs/parity-1.6.1.md`，接入形态 `docs/upgrade-2.0-runtime.md`，扩展目录 `docs/upgrade-2.0-extend.md`。

## 常用命令

体检工具随包分发：接入项目装好 `fxri/fxyin` 后用 `php vendor/bin/fxyin upgrade`；尚未接入的项目检出框架仓库，用 `php fxyin upgrade`，对目标项目只读运行。

```bash
# 项目画像：PHP、宿主框架、入口、扩展模块、调用量档位与阻塞项
php vendor/bin/fxyin upgrade profile --root=<项目> --markdown

# 旧 API 统计：按目录与能力域给出替换建议
php vendor/bin/fxyin upgrade scan --root=<项目> --markdown

# 生成只减不增的基线
php vendor/bin/fxyin upgrade baseline --root=<项目> --write --baseline=<文件>

# 校验基线：任一目录高于基线即失败，可直接用于流水线门禁
php vendor/bin/fxyin upgrade baseline --root=<项目> --check --baseline=<文件>
```

## 边界声明

- 不负责 PHP 版本升级本身；实际运行版本低于 8.2 时先完成 PHP 升级，再回到迁移。
- 不代替 Composer 接入：没有 `composer.json` 的项目先补接入，本技能只给出路线。
- 不把独立扩展模块计入迁移范围；哪些模块属于本次范围由项目判断。
- 体检工具只读，不 `include` 项目文件、不执行 Composer；它给出线索，不替项目做决策。
- 不重复响应与配置细节：五键信封、分页壳、配置递归与覆盖规则见 `fxri-fxyin` 技能对应篇目。
- 迁移完成后的删除动作与旧目录清理须由项目确认后再执行，技能不提供自动删除命令。
