---
"fxri/fxyin": minor
---

- 修改：对外响应统一为 `code`、`message`、`data`、`extend`、`debug` 五键，`code` 为整数、默认 200，`success` 只在内部保留
- 新增：分页壳 `Data\Page`（`total`、`records`、`page`、`size`），分页列表统一放在 `data.records`
- 修改：`Envelope`、`Responder`、JSON/XML 格式化器与 ThinkPHP 异常处理同步新口径，旧六元数组与 `isOk()` 别名不再保留
- 修改：本次属破坏性变更，按 2.2.0 发布，接入方需按迁移文档改写响应读取
