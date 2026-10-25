---
"fxri/fxyin": patch
---

修复：`File\Path::join()` 不再丢弃首段的前导斜杠，绝对路径拼接后仍是绝对路径（`join('/tmp', 'a')` 由 `tmp/a` 变为 `/tmp/a`），修复 Linux 下 `Http\View` 无法定位绝对路径模板的问题。
