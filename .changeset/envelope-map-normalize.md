---
"@fxri/fxyin": patch
---

- 修复：`Envelope::toArray()` 的 `extend` / `debug` 统一为具名映射，空值输出 `{}`、列表归入保留键 `items`，不再出现对象 / 数组翻转与数字键
- 修复：`extend()` / `debug()` 仍返回数组，PHP 侧取值方式不变
- 文档：`docs/http.md`、`docs/http-api.md` 与 `fxri-fxyin` 技能同步对象与 `items` 口径
