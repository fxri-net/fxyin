---
"@fxri/fxyin": patch
---

- 修复：`Envelope::toArray()` 的 `extend` 恒输出对象，空值在 JSON 侧为 `{}` 而非 `[]`，与统一信封的扩展对象契约一致
- 修复：`Envelope::extend()` 仍返回数组，PHP 侧调用方式不变；`debug` 的既有形态不改
- 文档：`docs/http.md`、`docs/http-api.md` 与 `fxri-fxyin` 技能的响应示例与字段说明同步为对象形态
