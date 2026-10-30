# AGENTS.md — 本仓库 AI 协作规则

适用于任何 AI 编码工具在 `fxri/fxyin` 仓库内开发。本文件随 git 分发，换人、换电脑、换工具均生效。**本规则只约束本仓库的开发过程，不适用于安装本包的业务项目。**

## 能力变更三方真实一致（强制质量门）

任何影响对外行为的变更——新增/修改/删除类、方法、配置键、`tools/` 命令与参数、响应与错误口径、skills 内容、文档中对其的结构性描述——在任务归档前必须完成三方复核，与 `toolkit tasks check` 同为不可绕过的质量门：

1. **能力自身**：源码实际行为、单元测试、`tools/` 命令帮助与 PHPDoc 三者一致。测试不得复刻被测实现的逻辑，派生与镜像类断言须直接调用真实实现。
2. **文档**：`docs/` 下全部 `.md` 与根 `README.md` 逐篇过触达面（不在此枚举清单，避免新增页面后漂移；`docs/changelog.md` 为生成物，只核对镜像结果与源一致）。凡触达处措辞必须与实际行为严格一致，包括「不自动修复」「需人工确认」等限制细节；确认无触达的内容须在对话中明确说明，不得静默跳过。
   ⚠️ **时点约束**：触达面核对在**变更方案确认时**即逐篇走查，结论列入任务文件「影响范围」节（每篇标注 已同步 / 无触达及理由），不延迟到归档时才复核。涉及术语或措辞变更时，先 grep 全仓库定位旧措辞触达点再列清单。
3. **skills**：`skills/` 下 `SKILL.md` 与 `references/` 同步新能力/新流程；每个 `SKILL.md` 的版本写两处且须一致——frontmatter 的 `metadata.version`（机器读取位）与正文首部 `> 本技能版本 x.y.z（随 fxri/fxyin 同批分发）`（进上下文的自证位）；内容变更时同步递增（小修 +patch，能力级 +minor）。
4. **变更集**：凡影响对外行为的变更，任务归档前必须在 `.changeset/` 建变更集（标注 patch/minor/major 并写用户可感知的描述，条目须逐条以 `- 类型：描述` 书写，`- ` 列表标记让每条独立成项，`类型：` 前缀作归类信号，前缀表见 `skills/fxri-release-changelog/SKILL.md`）；归档时出现「无变更集」提示即视为本质量门未过。
5. **全局规则薄引用**：可变流程细节只进 skills，不在用户手动复制的规则快照里复述；技能随包同源分发，升级即生效。
6. **规则层更新锚点**：本仓仅 `AGENTS.md` 承载本规则，不复制 `SPEC.md`、`docs/ai-rules.md`、`docs/commit-rules.md` 等外部载体；内容变更须与触发它的能力变更同批落盘。

### conventions 同步

`.tasks/conventions/` 的索引与 `AGENTS.md`、skills 条文存在对应关系：修改 `AGENTS.md` 或 skills 条文时，须同步检查索引对应行的「当前语义」是否需要更新；语义变更先在 `history.md` 留痕、再更新索引表内当前语义，随该变更同批落盘。

**动手前先读 `.tasks/conventions/index.md`**：先看端清单与索引了解本项目已确立哪些规范，条文以各文件的单一事实源为准。

## 验收方式

- 跑 `pnpm verify`——**单一步骤清单真源**，步骤定义在 `scripts/verify.mjs`，本地与 CI 调用同一份；**禁止在本文或 CI 配置里另写步骤枚举**，那是清单漂移的根因。任一步失败即不得交付。
- `toolkit tasks check` 的 error 与 warn 均应为 0（除非任务规格另有豁免）。
- grep 确认文档与 skills 措辞落地。
- 涉及配置递归、IDE 元数据等工具能力时，另跑对应单测。

## 协作流程约束

- 涉及代码编写、文件修改、命令执行的任务，先输出结构化实施方案（目标 / 实施步骤 / 影响范围 / 验证与回滚），经确认后执行。
- 动手前先做**建档评估**：涉及仓库文件改动的任务，先查 active/archive 判同主题——active 有同主题更新原文件、archive 有同主题且本次不产生仓库文件改动则不重复建档直接执行、均无则先建档再动手（判别细节以 fxri-plan-to-task 最新版为准）。
- 任务管理全流程走 toolkit skills（无任务区先 `toolkit init`，再建档、执行、归档、沉淀）。
- 任务收尾按「改完文件 → 归档 → `pnpm verify` → 提交」执行，**验收须晚于归档**：归档会改写 `.tasks/`，早于归档跑验收就覆盖不到终态。
- 任务时间字段按四级时间源取证，口径以 `skills/fxri-plan-to-task/references/task-spec.md` 最新版为准；禁止估算或时区换算。
- 代码注释只保留描述最终代码逻辑的必要注释，不留过程性解释。
- **路径书写**：仓库内引用一律用相对路径（含行号时形如 `src/Tooling/MetaGenerator.php L40`），不写本机绝对路径；归档时必须归一为占位形式。
- 提交信息遵循 `.trae/rules/git-commit-message.md`（若存在）或遵循：全中文标题、`类型：描述` 全角冒号、祈使句、30 汉字内。
- **降级不静默**：用户显式传入的值无法采纳时不得静默忽略——可继续则 stderr 告警说明「忽略了什么、回落到什么」，不可继续则报错并以非 0 退出码终止。

## 发版链路

按顺序分步执行，每步产物检查无误后再进行下一步：

1. **消费变更集**：`toolkit changelog version`——合并 `.changeset/`，生成 CHANGELOG 新版本块并升级版本号；人工润色条目后跑 `toolkit changelog --history format` 归一历史块。
2. **门禁校验**：`pnpm verify`——任一步失败即中止发版，不得进入提交、打标签、发布；关卡必须挡在发布之前。
3. **提交**：版本与 CHANGELOG 改动单独提交，不与任务收尾混批。
4. **打标签**：提交落盘后打 `vX.Y.Z` 标签并核对存在。
5. **发布**：推送到包仓库（Packagist）。
6. **推送**：遍历当前全部已配置远端逐一推送，推送前先列出远端清单并经用户确认。

## 本仓库能力自举

- skills：需要查阅技能指引时，直接读仓库内 `skills/` 下的 `SKILL.md` 与 `references/`，全局副本仅在发版后同步，仓库内与全局不一致时以仓库内为准。
- 工具：`php tools/upgrade-inspect.php`（profile/scan/baseline）、`php tools/install-skills.php`（`--target`、`--check`、`--root`）、`php tools/no-numeric-code.php`、`php tools/check-package.php`、`php tools/parity-audit.php`；CLI 帮助以脚本内 `usage()` 或其文件头注释为准。
