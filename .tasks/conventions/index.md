<!-- toolkit-conventions: v3 -->
# 项目协作规范索引

> 本文件为规范载体唯一入口与唯一权威；规范全貌见下方索引，条文见各分册。
> 读面纪律：`history.md` / `retired.md` 属归档面，仅在审计 / 恢复时读，不在常规读面。

## 一、端清单

> 端名与任务 frontmatter 的 `scope` 取值共用同一套词表；保留字 `index` / `common` 不得作端名。

| 端名 | 说明 |
| --- | --- |
| frame | 框架核心（`frame/`） |
| facade | 控制器门面（`app/controller/facade/`） |

## 二、索引

| ID | 规则 | 当前语义 | 归属 | 单一事实源 |
| --- | --- | --- | --- | --- |
| C-1 | 任务区不出现外部仓库实名 | `.tasks/` 内指称外部仓库一律用通用措辞（「接入方仓库」「被参考仓库」等），不得写实名 | common | [common.md](./common.md) |
| C-2 | 路径书写与归档归一 | 仓库内引用用仓库根相对路径；任务进行中可留本机资源路径，归档时归一为 `<本机>/…`、`~/` 或 `%USERPROFILE%` | common | [common.md](./common.md) |

## 三、用法说明

- **载体形态**：本项目采用「条文式」，条文全文落在本载体分册，本载体即项目唯一规范载体。
- **本项目约定**：`scope` 取端清单的 `frame` / `facade`，全端通用任务写 `common`。任务区文本治理中的「跨仓库改动各自归档」与「归档块不可变」已由全局规则与 `skills/fxri-plan-to-task` 承载，本载体不重复。
- **读写与维护**：读面与读写判定、写入归属、语义修订与退休流程一律见 `skills/fxri-plan-to-task/references/conventions-spec.md`，本载体不另述。
