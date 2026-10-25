# 变更集目录

本目录存放**变更集**：每个文件描述一处对使用者的变更及其 bump 类型（`patch` / `minor` / `major`），供发版时消费。

- `config.json`：changesets 数据模型配置（基础分支、访问级别等），无需手工改动。
- 创建变更集：`toolkit changelog`（等价 changesets 的 `add`），由全局 `@fxri/toolkit` 提供。
- 消费发版：由全局 `@fxri/toolkit` 的 `toolkit changelog version` 读取并合并，生成 `CHANGELOG.md` 新版本块并升级 `package.json` 版本号；本仓不依赖本地 `@changesets/cli`。
- 流程细节见文档站「贡献指南」页的发版链路。
