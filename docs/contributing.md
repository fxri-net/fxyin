# 贡献指南

本地环境、验收命令、提交规范、任务记录与发版链路，都收在这一页；各能力的具体用法看对应的参考页。

## 定位

本仓是一个 Composer 库，外加一套不发布的 Node 工具链：前者是 `src/` 下的框架代码，后者只负责文档站构建与发版辅助（`package.json` 为 `private: true`，不进 npm）。贡献场景大体分三类——改代码、改文档、发版本，三者的验收口径不同，但都要通过 `pnpm verify`。

## 开发环境

| 依赖 | 要求 |
| --- | --- |
| PHP | 8.2 起，CI 覆盖 8.2 至 8.5 |
| PHP 扩展 | `mbstring`、`openssl` |
| Composer | 2.x |
| Node.js | 20.19 起（见 `.node-version`） |
| pnpm | 10.x（`packageManager` 已锁定版本） |
| toolkit | 全局 `@fxri/toolkit`，用于变更集、发版与任务记录 |

首次准备全局工具：

```bash
pnpm add -g @fxri/toolkit
toolkit skills install
```

## 本地准备

```bash
git clone https://github.com/fxri-net/fxyin.git
cd fxyin
composer install
pnpm install
```

仓库不提交 `composer.lock`（已在 `.gitignore` 中），各 PHP 版本自行解析依赖；首次安装会本地生成锁文件，不要把它加入版本控制。

## 验收命令

| 命令 | 作用 |
| --- | --- |
| `composer check` | 聚合门禁，依次执行 `composer.json` 中 `scripts.check` 定义的步骤，CI 与本机共用同一条 |
| `composer validate --strict` | 校验 `composer.json` 结构与字段 |
| `composer cs` | `php-cs-fixer` 以 `--dry-run --diff` 检查代码风格 |
| `composer stan` | PHPStan 静态分析 |
| `composer test` | PHPUnit 全量测试 |
| `php fxyin scan` | 扫描数字枚举等禁用写法，等价于 `composer scan` |
| `vendor/bin/php-cs-fixer fix` | 自动修复代码风格，仅在需要本地修正时执行 |
| `pnpm docs:dev` | 本地起文档站开发服务 |
| `pnpm docs:build` | 构建文档站，同时校验站内链接 |
| `pnpm docs:preview` | 预览已构建的文档站产物 |
| `pnpm sync:changelog-doc` | 把根 `CHANGELOG.md` 同步为 `docs/changelog.md` |
| `pnpm verify` | 聚合门禁，按 `scripts/verify.mjs` 的清单依次执行；`--skip-code` / `--skip-docs` 可跳过代码段或文档段 |

提交前跑一次 `pnpm verify`；它是本地与流水线的统一入口，步骤清单只此一份，不要只挑其中几步执行。

命令行只有 `fxyin` 一个入口：仓库内 `php fxyin <命令>`，接入项目 `php vendor/bin/fxyin <命令>`；命令总览与退出码见[命令行](./cli)。

## 代码约定

- 每个 PHP 文件都声明 `declare(strict_types=1)`，命名空间以 `Fxri\` 开头，PSR-4 映射到 `src/`。
- 无状态能力写成 `final class` 加静态方法；有状态能力用私有构造加静态工厂 `make()`，实例不可变。
- 链式配置方法（如 `KernelBuilder::with*()`、`Shape` 的填充方法）返回新实例，不修改自身，避免装配过程留下副作用。
- 库内只抛异常，不 `echo`、不 `exit`、不 `die`；输出由接入层决定，`Responder` 是唯一的响应输出入口。
- 模式一律用具名枚举或链式方法表达，禁止数字枚举与数字 `type` 参数，`composer scan` 会拦截新增写法。
- 注释写中文，只解释「为什么这么做」，不复述代码本身。
- 新增类要同时补 `tests/` 用例与文档站对应参考页，避免代码与文档脱节。
- 版本号以 `package.json` 为工具链真源，`src/Package.php` 的 `VERSION` 必须与之一致，测试会做校验。

## 提交信息规范

标题全中文，格式为 `类型：描述`，使用全角冒号，祈使句，30 个汉字以内，句末不加句号，不使用 `feat:`、`fix:` 这类英文格式头。专有名词（包名、类名、命令名）可保留英文。

| 类型 | 适用场景 |
| --- | --- |
| 新增 | 新功能、新文件、新依赖 |
| 修改 | 修改已有功能、调整逻辑、替换内容 |
| 修复 | 修复 Bug |
| 优化 | 性能、体验、样式、配置、结构调整、依赖升级 |
| 清理 | 删除无用代码、清理依赖 |
| 文档 | 更新文档、注释 |

改动复杂时，标题后空一行，用 `- ` 列表说明变更点与原因。

## 任务记录流程

仓库用 `.tasks/` 记录任务：进行中的任务放在 `.tasks/active/年月/`，完成后按月归档到 `.tasks/archive/年月/年月日.md`。任务档案与代码改动一起提交；建档、执行、归档的判定与时间口径以全局技能 `fxri-plan-to-task` 为准。

```bash
toolkit init             # 首次初始化任务区，幂等，已有目录不覆盖
toolkit tasks archive    # 归档已完成任务，可加 --dry-run 预演
toolkit tasks check      # 校验任务档案结构，必须零错误
```

## 发版链路

发版能力全部来自全局 `@fxri/toolkit`，完整步骤以全局技能 `fxri-release-changelog` 为准；本仓只额外约束：

- 变更集条目逐条写成 `- 类型：描述`，影响对外行为的改动必须建变更集。
- 版本号与 `CHANGELOG.md` 的改动单独提交，不与任务收尾混批。
- 发布前必须跑 `pnpm verify`，任一步失败即中止，不得进入提交、打标签与发布。
- 根 `CHANGELOG.md` 是更新日志的源，`docs/changelog.md` 由 `pnpm sync:changelog-doc` 生成；两者不一致时 `pnpm verify` 会直接失败。
- ⚠️ `toolkit changelog --history format` 会连带扫描并改写 `vendor/**/CHANGELOG.md`，`vendor` 是 `composer install` 的再生产物且不入库，格式化后不要把这些变更带进提交。

### 标签规则

- 标签格式 `vX.Y.Z`，用附注标签；已推送的标签不回移、不覆盖。
- 补打历史标签时，按更新日志的日期边界选提交，并在日志标注「标签补打」及补打日期。
- 1.x 只补标不发版，`v1.6.1` 仅作终点标记；正式发版从 2.0.0 起。
- 发版后同步 `src/Package.php` 的 `VERSION`，`tests/PackageTest.php` 会校验它与 `package.json` 一致。

## 文档站维护

- 页面是 `docs/` 下的 Markdown，导航与侧栏在 `docs/.vitepress/config.mts`；新增页面必须同步加条目，否则只能靠直接输入地址访问。
- `docs/changelog.md` 由脚本生成，页面内已注明请勿手改；要改内容请改根 `CHANGELOG.md` 后重新同步。
- 构建期会校验 Markdown 内的站内链接，死链直接导致构建失败，改完文档跑一次 `pnpm docs:build`。
- 站点图片放在 `docs/public/`，`logo.png` 与 `og-image.png` 分别用于导航栏与社交分享卡片。

## 注意事项

- `vendor/`、`node_modules/`、`docs/.vitepress/cache/`、`docs/.vitepress/dist/`、`.phpunit.cache/` 都不入库，别把它们的改动带进提交。
- 示例代码里的密钥、口令一律从环境变量读取，不要在文档或测试里写真实凭据。
- 发版标签只由维护者创建；变更集里的 bump 类型要与实际影响一致，破坏性改动必须选 `major`。
- 文档描述的是当前 2.x 的行为，历史版本行为以[更新日志](./changelog)中的归档条目为准。
